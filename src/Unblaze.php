<?php

namespace Livewire\Blaze;

use Illuminate\Support\Arr;

class Unblaze
{
    static $unblazeScopes = [];
    static $unblazeReplacements = [];

    public static function storeScope($token, $scope = [])
    {
        static::$unblazeScopes[$token] = $scope;
    }

    public static function hasUnblaze(string $template): bool
    {
        return str_contains($template, '@unblaze');
    }

    public static function processUnblazeDirectives(string $template)
    {
        $compiler = BladeService::getHackedBladeCompiler();

        $expressionsByToken = [];

        $compiler->directive('unblaze', function ($expression) use (&$expressionsByToken) {
            $token = str()->random(10);

            $expressionsByToken[$token] = $expression;

            return '[STARTUNBLAZE:'.$token.']';
        });

        $compiler->directive('endunblaze', function () {
            return '[ENDUNBLAZE]';
        });

        $result = $compiler->compileStatementsMadePublic($template);

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

    public static function replaceUnblazePrecompiledDirectives(string $template)
    {
        if (str_contains($template, '[STARTCOMPILEDUNBLAZE')) {
            $template = preg_replace_callback('/(\[STARTCOMPILEDUNBLAZE:([0-9a-zA-Z]+)\])(.*?)(\[ENDCOMPILEDUNBLAZE\])/s', function ($matches) use (&$expressionsByToken) {
                $token = $matches[2];

                $innerContent = static::$unblazeReplacements[$token];
                $innerContent = app('blaze')->compileTags($innerContent);

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