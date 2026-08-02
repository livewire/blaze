<?php

namespace Livewire\Blaze;

use Livewire\Blaze\Compiler\DirectiveCompiler;
use Illuminate\Support\Str;

/**
 * Handles @unblaze directives by extracting their content from the Blaze pipeline
 * and re-injecting it after compilation with scope isolation.
 */
class Unblaze
{
    static $unblazeScopes = [];
    static $unblazeReplacements = [];

    /**
     * Store runtime scope data for an @unblaze token.
     */
    public static function storeScope($token, $scope = [])
    {
        static::$unblazeScopes[$token] = $scope;
    }

    /**
     * Check if a template contains @unblaze directives.
     */
    public static function hasUnblaze(string $template): bool
    {
        return str_contains($template, '@unblaze');
    }

    /**
     * Replace @unblaze/@endunblaze blocks with placeholders before Blaze compilation.
     */
    public static function processUnblazeDirectives(string $template)
    {
        $expressionsByToken = [];

        $result = DirectiveCompiler::make()
            ->directive('unblaze', function ($expression) use (&$expressionsByToken) {
                $token = str()->random(10);

                $expressionsByToken[$token] = $expression;

                return '[STARTUNBLAZE:'.$token.']';
            })
            ->directive('endunblaze', function () {
                return '[ENDUNBLAZE]';
            })
            ->compile($template);

        $result = preg_replace_callback('/(\[STARTUNBLAZE:([0-9a-zA-Z]+)\])(.*?)(\[ENDUNBLAZE\])/s', function ($matches) use (&$expressionsByToken) {
            $token = $matches[2];
            $expression = $expressionsByToken[$token];
            $innerContent = $matches[3];

            static::$unblazeReplacements[$token] = $innerContent;

            return ''
                . '[STARTCOMPILEDUNBLAZE:'.$token.']'
                . '<'.'?php \Livewire\Blaze\Unblaze::storeScope("'.$token.'", '.$expression.') ?>'
                . '[ENDCOMPILEDUNBLAZE:'.$token.']';
        }, $result);

        return $result;
    }

    /**
     * Restore @unblaze placeholders with their compiled content and scope wrappers.
     */
    public static function replaceUnblazePrecompiledDirectives(string $template)
    {
        if (str_contains($template, '[STARTCOMPILEDUNBLAZE')) {
            $template = preg_replace_callback('/(\[STARTCOMPILEDUNBLAZE:([0-9a-zA-Z:]+)?\])(.*?)(\[ENDCOMPILEDUNBLAZE:\2\])(\r?\n)?/s', function ($matches) use (&$expressionsByToken) {
                $token = $matches[2];

                // Because unblaze content is not available at render-time during folding,
                // its content wasn't trimmed when passsing through slots and components.
                // To compensate for this, we've added a :trim suffix during rendering
                // based on the surrounding content and now we'll reapply it here.
                if ($trim = Str::match('/:(ltrim|rtrim|trim)$/', $token)) {
                    $token = substr($token, 0, -(strlen($trim) + 1));
                }

                $innerContent = Blaze::compileForUnblaze(
                    static::$unblazeReplacements[$token]
                );

                $scope = static::$unblazeScopes[$token];

                $runtimeScopeString = var_export($scope, true);

                $whitespace = $matches[5] ?? '';

                // If the unblaze block was passed through a slot, we need to compensate
                // for the the php blocks eating the next new line.
                if ($trim === 'trim') {
                    $whitespace = $whitespace . $whitespace;
                }

                $result = ''
                    . '<'.'?php if (isset($scope)) $__scope = $scope; ?>'
                    . '<'.'?php $scope = '.$runtimeScopeString.'; ?>'
                    . ($trim ? $trim($innerContent) : $innerContent)
                    . '<'.'?php if (isset($__scope)) { $scope = $__scope; unset($__scope); } ?>'
                    . $whitespace;

                return $result;
            }, $template);
        }

        return $template;
    }

    /**
     * Clear all unblaze state.
     */
    public static function flushState()
    {
        static::$unblazeScopes = [];
        static::$unblazeReplacements = [];
    }
}