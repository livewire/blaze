<?php

namespace Livewire\Blaze;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Compilers\ComponentTagCompiler;
use Livewire\Blaze\Compiler\DirectiveCompiler;
use Livewire\Blaze\Runtime\BlazeRuntime;
use ReflectionClass;

class BladeService
{
    public function __construct(
        public BladeCompiler $compiler,
        public BlazeRuntime $runtime,
        public BlazeManager $manager,
    ) {}

    /**
     * Render a Blade template string in an isolated context.
     */
    public function render(string $template): string
    {
        return $this->isolatedRender($template);
    }

    /**
     * Get the temporary cache directory path used during isolated rendering.
     */
    public function getTemporaryCachePath(): string
    {
        return config('view.compiled').'/blaze';
    }

    /**
     * Render a Blade template string in isolation by freezing and restoring compiler state.
     */
    public function isolatedRender(string $template): string
    {
        $compiler = $this->compiler;

        $temporaryCachePath = $this->getTemporaryCachePath();

        File::ensureDirectoryExists($temporaryCachePath);

        $factory = app('view');

        [$factory, $restoreFactory] = $this->freezeObjectProperties($factory, [
            'renderCount' => 0,
            'renderedOnce' => [],
            'sections' => [],
            'sectionStack' => [],
            'pushes' => [],
            'prepends' => [],
            'pushStack' => [],
            'componentStack' => [],
            'componentData' => [],
            'currentComponentData' => [],
            'slots' => [],
            'slotStack' => [],
            'fragments' => [],
            'fragmentStack' => [],
            'loopsStack' => [],
            'translationReplacements' => [],
        ]);

        [$compiler, $restore] = $this->freezeObjectProperties($compiler, [
            'cachePath' => $temporaryCachePath,
            'rawBlocks' => [],
            'footer' => [],
            'prepareStringsForCompilationUsing' => [
                function ($input) use ($compiler) {
                    if (Unblaze::hasUnblaze($input)) {
                        $input = Unblaze::processUnblazeDirectives($input);
                    };

                    $input = $this->manager->compileForFolding($input, $compiler->getPath());

                    return $input;
                },
            ],
            'path' => null,
            'forElseCounter' => 0,
            'firstCaseInSwitch' => true,
            'lastSection' => null,
            'lastFragment' => null,
        ]);

        [$runtime, $restoreRuntime] = $this->freezeObjectProperties($this->runtime, [
            'compiled' => [],
            'paths' => [],
            'compiledPath' => $temporaryCachePath,
            'dataStack' => [],
            'slotsStack' => [],
        ]);

        try {
            $this->manager->startFolding();

            $result = $compiler->render($template, deleteCachedView: true);
        } finally {
            $restore();
            $restoreFactory();
            $restoreRuntime();

            $this->manager->stopFolding();
        }

        $result = Unblaze::replaceUnblazePrecompiledDirectives($result);

        return $result;
    }

    /**
     * Delete the temporary cache directory created during isolated rendering.
     */
    public function deleteTemporaryCacheDirectory(): void
    {
        File::deleteDirectory($this->getTemporaryCachePath());
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
        $compiler = $this->compiler;

        app()->booted(function () use ($callback, $compiler) {
            $compiler->prepareStringsForCompilationUsing(function ($input) use ($callback, $compiler) {
                // We call getPath() on the captured $compiler instance rather than resolving it
                // via app('blade.compiler')->getPath() inside BlazeManager, this fixes #43.

                // Packages like Sentry force blade resolution during boot using app('view')->getEngineResolver()->resolve('blade').
                // When Laravel runs `config:cache` as part of `optimize`, it swaps the application instance in the container,
                // but later in `view:cache` it uses the original app instance from $this->laravel to compile the views.
                // Because of the early resolution, Laravel doesn't resolve blade compiler again from the new instance
                // and runs compile() on the stale one. Calling app('blade.compiler') returns a different instance
                // than the one used to compile the view, therefore $path isn't set and getPath() returns null.
                $path = $compiler->getPath();

                return $callback($input, $path);
            });
        });
    }

    /**
     * Invoke the Blade compiler's storeUncompiledBlocks via reflection.
     */
    public function preStoreUncompiledBlocks(string $input): string
    {
        $compiler = $this->compiler;
        $reflection = new \ReflectionClass($compiler);
        
        $storeRawBlock = $reflection->getMethod('storeRawBlock');

        $output = $input;

        $output = preg_replace_callback('/(?<!@)@verbatim(\s*)(.*?)@endverbatim/s', function ($matches) use ($storeRawBlock, $compiler) {
            return $matches[1].$storeRawBlock->invoke($compiler, "@verbatim{$matches[2]}@endverbatim");
        }, $output);

        $output = preg_replace_callback('/(?<!@)@php(.*?)@endphp/s', function ($matches) use ($storeRawBlock, $compiler) {
            return $storeRawBlock->invoke($compiler, "@php{$matches[1]}@endphp");
        }, $output);
        
        return $output;
    }

    /**
     * Store only @verbatim blocks as raw block placeholders.
     */
    public function storeVerbatimBlocks(string $input): string
    {
        $compiler = $this->compiler;

        $reflection = new \ReflectionClass($compiler);
        $method = $reflection->getMethod('storeVerbatimBlocks');

        return $method->invoke($compiler, $input);
    }

    /**
     * Restore raw block placeholders to their original content.
     */
    public function restoreRawBlocks(string $input): string
    {
        $compiler = $this->compiler;

        $reflection = new \ReflectionClass($compiler);
        $method = $reflection->getMethod('restoreRawContent');

        return $method->invoke($compiler, $input);
    }

    /**
     * Restore raw block placeholders to their original content.
     */
    public function restorePhpBlocks(string $input): string
    {
        $compiler = $this->compiler;

        $reflection = new \ReflectionClass($compiler);
        $method = $reflection->getMethod('restorePhpBlocks');

        return $method->invoke($compiler, $input);
    }

    /**
     * Invoke the Blade compiler's compileComments via reflection.
     */
    public function compileComments(string $input): string
    {
        $compiler = $this->compiler;

        $reflection = new \ReflectionClass($compiler);
        $compileComments = $reflection->getMethod('compileComments');

        return $compileComments->invoke($compiler, $input);
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
        $compiler = new ComponentTagCompiler(blade: $this->compiler);

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
        })->call($compiler, $attributeString);
    }

    public function compileUseStatements(string $input): string
    {
        return DirectiveCompiler::make()->directive('use', function ($expression) {
            $compiler = $this->compiler;

            $reflection = new \ReflectionClass($compiler);
            $method = $reflection->getMethod('compileUse');

            return $method->invoke($compiler, $expression);
        })->compile($input);
    }

    /**
     * Compile Blade echo syntax within attribute values using ComponentTagCompiler.
     */
    public function compileAttributeEchos(string $input): string
    {
        $compiler = new ComponentTagCompiler(blade: $this->compiler);

        $reflection = new \ReflectionClass($compiler);
        $method = $reflection->getMethod('compileAttributeEchos');

        return Str::unwrap("'".$method->invoke($compiler, $input)."'", "''.", ".''");
    }

    /**
     * Strip surrounding quotes from a string using ComponentTagCompiler.
     */
    public function stripQuotes(string $input): string
    {
        return (new ComponentTagCompiler(blade: $this->compiler))->stripQuotes($input);
    }

    /**
     * Register a callback to intercept view cache invalidation events.
     */
    public function viewCacheInvalidationHook(callable $callback): void
    {
        $compiler = $this->compiler;

        Event::listen('composing:*', function ($event, $params) use ($callback, $compiler) {
            $view = $params[0];

            if (! $view instanceof \Illuminate\View\View) {
                return;
            }

            $invalidate = fn () => $compiler->compile($view->getPath());

            $callback($view, $invalidate);
        });
    }

    /**
     * Resolve a component name to its file path using registered anonymous component paths.
     */
    public function componentNameToPath($name): string
    {
        $compiler = $this->compiler;
        $viewFinder = app('view')->getFinder();

        $reflection = new \ReflectionClass($compiler);
        $pathsProperty = $reflection->getProperty('anonymousComponentPaths');
        $paths = $pathsProperty->getValue($compiler) ?? [];

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

    /**
     * Snapshot object properties and return a restore closure to revert them.
     */
    protected function freezeObjectProperties(object $object, array $properties)
    {
        $reflection = new ReflectionClass($object);

        $frozen = [];

        foreach ($properties as $key => $value) {
            $name = is_numeric($key) ? $value : $key;

            $property = $reflection->getProperty($name);

            $frozen[$name] = $property->getValue($object);

            if (! is_numeric($key)) {
                $property->setValue($object, $value);
            }
        }

        return [
            $object,
            function () use ($reflection, $object, $frozen) {
                foreach ($frozen as $name => $value) {
                    $property = $reflection->getProperty($name);
                    $property->setValue($object, $value);
                }
            },
        ];
    }
}
