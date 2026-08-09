<?php

namespace Livewire\Blaze;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Compilers\ComponentTagCompiler;
use Illuminate\View\Factory;
use Livewire\Blaze\Parser\Attribute;
use ReflectionClass;

class BladeService
{
    protected ComponentTagCompiler $tagCompiler;
    
    protected ReflectionClass $compilerReflection;
    protected ReflectionClass $tagCompilerReflection;

    protected ?array $customConditions = null;

    public function __construct(
        public BladeCompiler $compiler,
        protected Factory $view,
    ) {
        $this->tagCompiler = new ComponentTagCompiler(
            $compiler->getClassComponentAliases(),
            $compiler->getClassComponentNamespaces(),
            $compiler,
        );

        $this->compilerReflection = new ReflectionClass($this->compiler);
        $this->tagCompilerReflection = new ReflectionClass($this->tagCompiler);
    }

    /**
     * Check if template content is a Laravel exception view.
     */
    public function containsLaravelExceptionView(string $input): bool
    {
        return str_contains($input, 'laravel-exceptions');
    }

    /**
     * Register a callback to run at the earliest Blade pre-compilation phase.
     */
    public function earliestPreCompilationHook(callable $callback): void
    {
        app()->booted(function () use ($callback) {
            $this->compiler->prepareStringsForCompilationUsing(function ($input) use ($callback) {
                return $callback($input, $this->compiler->getPath());
            });
        });
    }

    /**
     * Invoke the Blade compiler's compileComments via reflection.
     */
    public function compileComments(string $input): string
    {
        $compileComments = $this->compilerReflection->getMethod('compileComments');

        return $compileComments->invoke($this->compiler, $input);
    }

    /**
     * Invoke the Blade compiler's hasEvenNumberOfParentheses via reflection.
     */
    public function hasEvenNumberOfParentheses(string $expression): bool
    {
        $method = $this->compilerReflection->getMethod('hasEvenNumberOfParentheses');

        return $method->invoke($this->compiler, $expression);
    }

    /**
     * Preprocess a component attribute string using Laravel's ComponentTagCompiler.
     *
     * Runs all five of Laravel's preprocessing transforms:
     *   :$foo        → :foo="$foo"           (parseShortAttributeSyntax)
     *   {{ $attrs }} → :attributes="$attrs"  (parseAttributeBag)
     *   @class(...)  → :class="..."          (parseComponentTagClassStatements)
     *   @style(...)  → :style="..."          (parseComponentTagStyleStatements)
     *   :attr=       → bind:attr=            (parseBindAttributes)
     */
    public function preprocessAttributeString(string $attributeString): string
    {
        // Laravel expects a space at the start of the attribute string...
        $attributeString = Str::start($attributeString, ' ');

        return (function (string $str): string {
            /** @var ComponentTagCompiler $this */
            $str = $this->parseShortAttributeSyntax($str);
            $str = $this->parseAttributeBag($str);
            $str = $this->parseComponentTagClassStatements($str);
            $str = $this->parseComponentTagStyleStatements($str);
            $str = $this->parseBindAttributes($str);

            return $str;
        })->call($this->tagCompiler, $attributeString);
    }

    public function compileUseStatements(string $expression): string
    {
        $method = $this->compilerReflection->getMethod('compileUse');

        return $method->invoke($this->compiler, $expression);
    }

    /**
     * Get the custom conditional directives registered with the Blade compiler.
     */
    public function customConditions(): array
    {
        if ($this->customConditions !== null) {
            return $this->customConditions;
        }

        $conditions = $this->compilerReflection->getProperty('conditions')->getValue($this->compiler);

        return $this->customConditions = collect($conditions)->keys()->all();
    }

    /**
     * Compile an attribute to a PHP array entry string (e.g. "'propName' => value").
     */
    public function compileAttribute(Attribute $attribute, bool $escapeBound = false, bool $originalKey = false): string
    {
        $key = $originalKey ? $attribute->name : $attribute->propName;

        if ($attribute->valueless) {
            $value = 'true';
        } elseif ($attribute->bound()) {
            $value = $escapeBound
                ? '\Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(' . $attribute->value . ')'
                : $attribute->value;
        } else {
            $value = $this->compileAttributeEchos($attribute->value);
        }

        return "'{$key}' => {$value}";
    }

    /**
     * Compile Blade echo syntax within attribute values using ComponentTagCompiler.
     */
    public function compileAttributeEchos(string $input): string
    {
        $method = $this->tagCompilerReflection->getMethod('compileAttributeEchos');

        return Str::unwrap("'".$method->invoke($this->tagCompiler, $input)."'", "''.", ".''");
    }

    /**
     * Strip surrounding quotes from a string using ComponentTagCompiler.
     */
    public function stripQuotes(string $input): string
    {
        return $this->tagCompiler->stripQuotes($input);
    }

    /**
     * Register a callback to intercept view cache invalidation events.
     */
    public function viewCacheInvalidationHook(callable $callback): void
    {
        Event::listen('composing:*', function ($event, $params) use ($callback) {
            $view = $params[0];

            if (! $view instanceof \Illuminate\View\View) {
                return;
            }

            $invalidate = fn () => $this->compiler->compile($view->getPath());

            $callback($view, $invalidate);
        });
    }

    /**
     * Resolve a component name to its file path.
     *
     * @see ComponentTagCompiler::componentClass()
     */
    public function componentNameToPath($name): string
    {
        if ($this->hasClassBasedComponent($name)) {
            return '';
        }

        $finder = $this->view->getFinder();

        $aliases = $this->compiler->getClassComponentAliases();

        if (isset($aliases[$name]) && $this->view->exists($aliases[$name])) {
            return $finder->find($aliases[$name]);
        }

        if (! is_null($guess = $this->guessAnonymousComponentUsingNamespaces($this->view, $name)) ||
            ! is_null($guess = $this->guessAnonymousComponentUsingPaths($this->view, $name))) {
            return $finder->find($guess);
        }

        return '';
    }

    /**
    * Check if the Blade compiler has any echo handlers registered.
    */
    public function hasEchoHandlers(): bool
    {
        $handlers = $this->compilerReflection->getProperty('echoHandlers')->getValue($this->compiler);

        return ! empty($handlers);
    }

    /**
     * Determine if a component resolves to a class rather than a blade view.
     *
     * @see ComponentTagCompiler::componentClass()
     */
    protected function hasClassBasedComponent(string $name): bool
    {
        $aliases = $this->compiler->getClassComponentAliases();

        if (isset($aliases[$name]) && class_exists($aliases[$name])) {
            return true;
        }

        if ($this->tagCompiler->findClassByComponent($name)) {
            return true;
        }

        if (class_exists($class = $this->tagCompiler->guessClassName($name))) {
            return true;
        }

        if (class_exists($class.'\\'.Str::afterLast($class, '\\'))) {
            return true;
        }

        return false;
    }

    protected function guessAnonymousComponentUsingNamespaces(Factory $viewFactory, string $component): string|null
    {
        $method = $this->tagCompilerReflection->getMethod('guessAnonymousComponentUsingNamespaces');

        return $method->invoke($this->tagCompiler, $viewFactory, $component);
    }

    protected function guessAnonymousComponentUsingPaths(Factory $viewFactory, string $component): string|null
    {
        $method = $this->tagCompilerReflection->getMethod('guessAnonymousComponentUsingPaths');

        return $method->invoke($this->tagCompiler, $viewFactory, $component);
    }
}
