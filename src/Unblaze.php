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
        $compiler = static::getHackedBladeCompiler();

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

    public static function getHackedBladeCompiler()
    {
        $instance = new class (
            app('files'),
            storage_path('framework/views'),
        ) extends \Illuminate\View\Compilers\BladeCompiler {
            /**
             * Make this method public...
             */
            public function compileStatementsMadePublic($template)
            {
                return $this->compileStatements($template);
            }

            /**
             * Tweak this method to only process custom directives so we
             * can restrict rendering solely to @island related directives...
             */
            protected function compileStatement($match)
            {
                if (str_contains($match[1], '@')) {
                    $match[0] = isset($match[3]) ? $match[1].$match[3] : $match[1];
                } elseif (isset($this->customDirectives[$match[1]])) {
                    $match[0] = $this->callCustomDirective($match[1], Arr::get($match, 3));
                } elseif (method_exists($this, $method = 'compile'.ucfirst($match[1]))) {
                    // Don't process through built-in directive methods...
                    // $match[0] = $this->$method(Arr::get($match, 3));

                    // Just return the original match...
                    return $match[0];
                } else {
                    return $match[0];
                }

                return isset($match[3]) ? $match[0] : $match[0].$match[2];
            }
        };

        return $instance;
    }
}