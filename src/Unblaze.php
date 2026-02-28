<?php

namespace Livewire\Blaze;

use Illuminate\Support\Arr;
use Livewire\Blaze\Compiler\DirectiveCompiler;

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
                . '[ENDCOMPILEDUNBLAZE]';
        }, $result);

        return $result;
    }

    /**
     * Restore @unblaze placeholders with their compiled content and scope wrappers.
     */
    public static function replaceUnblazePrecompiledDirectives(string $template)
    {
        if (str_contains($template, '[STARTCOMPILEDUNBLAZE')) {
            $template = preg_replace_callback('/(\[STARTCOMPILEDUNBLAZE:([0-9a-zA-Z]+)\])(.*?)(\[ENDCOMPILEDUNBLAZE\])/s', function ($matches) use (&$expressionsByToken) {
                $token = $matches[2];

                $innerContent = Blaze::compileForUnblaze(
                    static::$unblazeReplacements[$token]
                );

                $scope = static::$unblazeScopes[$token];

                $runtimeScopeString = var_export($scope, true);

                return ''
                    . '<'.'?php if (isset($scope)) $__scope = $scope; ?>'
                    . '<'.'?php $scope = '.$runtimeScopeString.'; ?>'
                    . $innerContent
                    . '<'.'?php if (isset($__scope)) { $scope = $__scope; unset($__scope); } ?>';
            }, $template);
        }

        return $template;
    }
}