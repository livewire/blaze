<?php

namespace Livewire\Blaze;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Compilers\ComponentTagCompiler;
use Livewire\Blaze\Compiler\DirectiveCompiler;
use Livewire\Blaze\Support\LaravelRegex;
use ReflectionClass;

class BladeService
{
    protected ComponentTagCompiler $tagCompiler;

    public function __construct(
        public BladeCompiler $compiler,
    ) {
        $this->tagCompiler = new ComponentTagCompiler(blade: $compiler);
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
     * Invoke the Blade compiler's storeUncompiledBlocks via reflection.
     */
    public function preStoreUncompiledBlocks(string $input): string
    {
        $reflection = new \ReflectionClass($this->compiler);
        
        $storeRawBlock = $reflection->getMethod('storeRawBlock');

        $output = $input;

        $output = preg_replace_callback(LaravelRegex::VERBATIM_BLOCK, function ($matches) use ($storeRawBlock) {
            return $matches[1].$storeRawBlock->invoke($this->compiler, "@verbatim{$matches[2]}@endverbatim");
        }, $output);

        $output = preg_replace_callback(LaravelRegex::PHP_BLOCK, function ($matches) use ($storeRawBlock) {
            return $storeRawBlock->invoke($this->compiler, "@php{$matches[1]}@endphp");
        }, $output);
        
        return $output;
    }

    /**
     * Store only @verbatim blocks as raw block placeholders.
     */
    public function storeVerbatimBlocks(string $input): string
    {
        $reflection = new \ReflectionClass($this->compiler);
        $method = $reflection->getMethod('storeVerbatimBlocks');

        return $method->invoke($this->compiler, $input);
    }

    /**
     * Restore raw block placeholders to their original content.
     */
    public function restoreRawBlocks(string $input): string
    {
        $reflection = new \ReflectionClass($this->compiler);
        $method = $reflection->getMethod('restoreRawContent');

        return $method->invoke($this->compiler, $input);
    }

    /**
     * Restore raw block placeholders to their original content.
     */
    public function restorePhpBlocks(string $input): string
    {
        $reflection = new \ReflectionClass($this->compiler);
        $method = $reflection->getMethod('restorePhpBlocks');

        return $method->invoke($this->compiler, $input);
    }

    /**
     * Invoke the Blade compiler's compileComments via reflection.
     */
    public function compileComments(string $input): string
    {
        $reflection = new \ReflectionClass($this->compiler);
        $compileComments = $reflection->getMethod('compileComments');

        return $compileComments->invoke($this->compiler, $input);
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

    public function compileUseStatements(string $input): string
    {
        return DirectiveCompiler::make()->directive('use', function ($expression) {
            $reflection = new \ReflectionClass($this->compiler);
            $method = $reflection->getMethod('compileUse');

            return $method->invoke($this->compiler, $expression);
        })->compile($input);
    }

    /**
     * Compile Blade echo syntax within attribute values using ComponentTagCompiler.
     */
    public function compileAttributeEchos(string $input): string
    {
        $reflection = new \ReflectionClass($this->tagCompiler);
        $method = $reflection->getMethod('compileAttributeEchos');

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
     * Resolve a component name to its file path using registered anonymous component paths.
     */
    public function componentNameToPath($name): string
    {
        $viewFinder = app('view')->getFinder();

        $reflection = new \ReflectionClass($this->compiler);
        $pathsProperty = $reflection->getProperty('anonymousComponentPaths');
        $paths = $pathsProperty->getValue($this->compiler) ?? [];

        if (str_contains($name, '::')) {
            [$namespace, $componentName] = explode('::', $name, 2);
            $componentPath = str_replace('.', '/', $componentName);

            foreach ($paths as $pathData) {
                if (isset($pathData['prefix']) && $pathData['prefix'] === $namespace) {
                    $basePath = rtrim($pathData['path'], '/');

                    $fullPath = $basePath.'/'.$componentPath.'.blade.php';
                    if (file_exists($fullPath)) {
                        return $fullPath;
                    }

                    $indexPath = $basePath.'/'.$componentPath.'/index.blade.php';
                    if (file_exists($indexPath)) {
                        return $indexPath;
                    }

                    $lastSegment = basename($componentPath);
                    $sameNamePath = $basePath.'/'.$componentPath.'/'.$lastSegment.'.blade.php';
                    if (file_exists($sameNamePath)) {
                        return $sameNamePath;
                    }
                }
            }

            try {
                $viewName = str_replace('::', '::components.', $name);
                return $viewFinder->find($viewName);
            } catch (\Exception $e) {
                try {
                    return $viewFinder->find(str_replace('::', '::components.', $name).'.index');
                } catch (\Exception $e2) {
                    return '';
                }
            }
        }

        $componentPath = str_replace('.', '/', $name);

        foreach ($paths as $pathData) {
            if (! isset($pathData['prefix']) || $pathData['prefix'] === null) {
                $registeredPath = $pathData['path'] ?? $pathData;

                if (is_string($registeredPath)) {
                    $basePath = rtrim($registeredPath, '/');

                    $fullPath = $basePath.'/'.$componentPath.'.blade.php';
                    if (file_exists($fullPath)) {
                        return $fullPath;
                    }

                    $indexPath = $basePath.'/'.$componentPath.'/index.blade.php';
                    if (file_exists($indexPath)) {
                        return $indexPath;
                    }

                    $lastSegment = basename($componentPath);
                    $sameNamePath = $basePath.'/'.$componentPath.'/'.$lastSegment.'.blade.php';
                    if (file_exists($sameNamePath)) {
                        return $sameNamePath;
                    }
                }
            }
        }

        try {
            return $viewFinder->find("components.{$name}");
        } catch (\Exception $e) {
            try {
                return $viewFinder->find("components.{$name}.index");
            } catch (\Exception $e2) {
                return '';
            }
        }
    }

}
