<?php

namespace Livewire\Blaze\Compiler;

use Illuminate\Support\Arr;

/**
 * Compiles individual Blade directives using a sandboxed compiler
 * that only processes custom directives and ignores built-in ones.
 */
class DirectiveCompiler
{
    /** @var array<string, callable> */
    protected array $directives = [];

    /**
     * Create a new DirectiveCompiler instance.
     */
    public static function make(): static
    {
        return new static();
    }

    /**
     * Register a directive on this compiler instance.
     */
    public function directive(string $name, callable $handler): static
    {
        $this->directives[$name] = $handler;

        return $this;
    }

    /**
     * Compile all registered directives within a template using a sandboxed Blade compiler.
     */
    public function compile(string $template): string
    {
        $compiler = $this->createSandboxedCompiler();

        foreach ($this->directives as $name => $handler) {
            $compiler->directive($name, $handler);
        }

        return $compiler->compileStatementsMadePublic($template);
    }

    /**
     * Create a BladeCompiler that only processes custom directives, ignoring built-in ones.
     */
    private function createSandboxedCompiler()
    {
        return new class(app('files'), config('view.compiled')) extends \Illuminate\View\Compilers\BladeCompiler
        {
            public function compileStatementsMadePublic($template)
            {
                $result = '';

                foreach (token_get_all($template) as $token) {
                    if (! is_array($token)) {
                        $result .= $token;

                        continue;
                    }
    
                    [$id, $content] = $token;

                    if ($id == T_INLINE_HTML) {
                        $result .= $this->compileStatements($content);
                    } else {
                        $result .= $content;
                    }
                }

                return $result;
            }

            /**
             * Only process custom directives, skip built-in ones.
             */
            protected function compileStatement($match)
            {
                if (str_contains($match[1], '@')) {
                    $match[0] = isset($match[3]) ? $match[1].$match[3] : $match[1];
                } elseif (isset($this->customDirectives[$match[1]])) {
                    $match[0] = $this->callCustomDirective($match[1], Arr::get($match, 3));
                } elseif (method_exists($this, $method = 'compile'.ucfirst($match[1]))) {
                    return $match[0];
                } else {
                    return $match[0];
                }

                return isset($match[3]) ? $match[0] : $match[0].$match[2];
            }
        };
    }
}
