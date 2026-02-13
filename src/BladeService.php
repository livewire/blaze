<?php

namespace Livewire\Blaze;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\View\Compilers\ComponentTagCompiler;
use ReflectionClass;

class BladeService
{
    /**
     * Render a Blade template string in an isolated context.
     */
    public static function render(string $template): string
    {
        return static::isolatedRender($template);
    }

    /**
     * Compile a single directive within a template using a sandboxed Blade compiler.
     */
    public static function compileDirective(string $template, string $directive, callable $callback)
    {
        // Protect raw block placeholders so restoreRawContent doesn't resolve them
        $template = preg_replace('/@__raw_block_(\d+)__@/', '__BLAZE_RAW_BLOCK_$1__', $template);

        $compiler = static::getHackedBladeCompiler();

        $compiler->directive($directive, $callback);

        $result = $compiler->compileStatementsMadePublic($template);

        return preg_replace('/__BLAZE_RAW_BLOCK_(\d+)__/', '@__raw_block_$1__@', $result);
    }

    /**
     * Create a BladeCompiler that only processes custom directives, ignoring built-in ones.
     */
    public static function getHackedBladeCompiler()
    {
        $instance = new class(app('files'), config('view.compiled')) extends \Illuminate\View\Compilers\BladeCompiler
        {
            public function compileStatementsMadePublic($template)
            {
                $template = $this->storeUncompiledBlocks($template);
                $template = $this->compileComments($template);
                $template = $this->compileStatements($template);
                $template = $this->restoreRawContent($template);

                return $template;
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

        return $instance;
    }

    /**
     * Get the temporary cache directory path used during isolated rendering.
     */
    public static function getTemporaryCachePath(): string
    {
        return config('view.compiled').'/blaze';
    }

    /**
     * Render a Blade template string in isolation by freezing and restoring compiler state.
     */
    public static function isolatedRender(string $template): string
    {
        $compiler = app('blade.compiler');

        $temporaryCachePath = static::getTemporaryCachePath();

        File::ensureDirectoryExists($temporaryCachePath);

        $factory = app('view');

        [$factory, $restoreFactory] = static::freezeObjectProperties($factory, [
            'componentStack' => [],
            'componentData' => [],
            'currentComponentData' => [],
            'slots' => [],
            'slotStack' => [],
            'renderCount' => 0,
        ]);

        $isTopLevelTemplate = true;

        [$compiler, $restore] = static::freezeObjectProperties($compiler, [
            'cachePath' => $temporaryCachePath,
            'rawBlocks',
            'prepareStringsForCompilationUsing' => [
                function ($input) use (&$isTopLevelTemplate) {
                    // Only process @unblaze for the top-level template being folded.
                    // Nested compilations (triggered by ensureCompiled for inner components)
                    // should not have their @unblaze processed here, as the markers would
                    // end up in the compiled function files without being replaced.
                    if ($isTopLevelTemplate && Unblaze::hasUnblaze($input)) {
                        $input = Unblaze::processUnblazeDirectives($input);
                    }
                    
                    $isTopLevelTemplate = false;

                    $input = Blaze::compileForFolding($input);

                    return $input;
                },
            ],
            'path' => null,
        ]);

        [$runtime, $restoreRuntime] = static::freezeObjectProperties(app('blaze.runtime'), [
            'compiled' => [],
            'paths' => [],
            'compiledPath' => $temporaryCachePath,
        ]);

        try {
            // Blade's string rendering writes to `view.compiled` rather than `cachePath`,
            // so `deleteCachedView: true` ensures cleanup of the generated file
            $result = $compiler->render($template, deleteCachedView: true);

            $result = Unblaze::replaceUnblazePrecompiledDirectives($result);
        } finally {
            $restore();
            $restoreFactory();
            $restoreRuntime();
        }

        return $result;
    }

    /**
     * Delete the temporary cache directory created during isolated rendering.
     */
    public static function deleteTemporaryCacheDirectory(): void
    {
        File::deleteDirectory(static::getTemporaryCachePath());
    }

    /**
     * Check if template content is a Laravel exception view.
     */
    public static function containsLaravelExceptionView(string $input): bool
    {
        return str_contains($input, 'laravel-exceptions');
    }

    /**
     * Register a callback to run at the earliest Blade pre-compilation phase.
     */
    public static function earliestPreCompilationHook(callable $callback): void
    {
        app()->booted(function () use ($callback) {
            app('blade.compiler')->prepareStringsForCompilationUsing(function ($input) use ($callback) {
                $output = $callback($input);

                return $output;
            });
        });
    }

    /**
     * Invoke the Blade compiler's storeUncompiledBlocks via reflection.
     */
    public static function preStoreUncompiledBlocks(string $input): string
    {
        $compiler = app('blade.compiler');

        $reflection = new \ReflectionClass($compiler);
        $storeVerbatimBlocks = $reflection->getMethod('storeUncompiledBlocks');

        return $storeVerbatimBlocks->invoke($compiler, $input);
    }

    /**
     * Invoke the Blade compiler's compileComments via reflection.
     */
    public static function compileComments(string $input): string
    {
        $compiler = app('blade.compiler');

        $reflection = new \ReflectionClass($compiler);
        $compileComments = $reflection->getMethod('compileComments');

        return $compileComments->invoke($compiler, $input);
    }

    /**
     * Preprocess a component attribute string using Laravel's ComponentTagCompiler.
     *
     * Transforms {{ $attributes... }} into :attributes="...",
     * @class(...) into :class="...", and @style(...) into :style="...".
     */
    public static function preprocessAttributeString(string $attributeString): string
    {
        $compiler = new ComponentTagCompiler(blade: app('blade.compiler'));

        return (function (string $str): string {
            /** @var ComponentTagCompiler $this */
            $str = $this->parseAttributeBag($str);
            $str = $this->parseComponentTagClassStatements($str);
            $str = $this->parseComponentTagStyleStatements($str);

            return $str;
        })->call($compiler, $attributeString);
    }

    /**
     * Compile Blade echo syntax within attribute values using ComponentTagCompiler.
     */
    public static function compileAttributeEchos(string $input): string
    {
        $compiler = new ComponentTagCompiler(blade: app('blade.compiler'));

        $reflection = new \ReflectionClass($compiler);
        $method = $reflection->getMethod('compileAttributeEchos');

        return Str::unwrap("'".$method->invoke($compiler, $input)."'", "''.", ".''");
    }

    /**
     * Register a callback to intercept view cache invalidation events.
     */
    public static function viewCacheInvalidationHook(callable $callback): void
    {
        Event::listen('composing:*', function ($event, $params) use ($callback) {
            $view = $params[0];

            if (! $view instanceof \Illuminate\View\View) {
                return;
            }

            $invalidate = fn () => app('blade.compiler')->compile($view->getPath());

            $callback($view, $invalidate);
        });
    }

    /**
     * Resolve a component name to its file path using registered anonymous component paths.
     */
    public static function componentNameToPath($name): string
    {
        $compiler = app('blade.compiler');
        $viewFinder = app('view.finder');

        $reflection = new \ReflectionClass($compiler);
        $pathsProperty = $reflection->getProperty('anonymousComponentPaths');
        $pathsProperty->setAccessible(true);
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
                return $viewFinder->find(str_replace('::', '::components.', $name));
            } catch (\Exception $e) {
                return '';
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
            return '';
        }
    }

    /**
     * Snapshot object properties and return a restore closure to revert them.
     */
    protected static function freezeObjectProperties(object $object, array $properties)
    {
        $reflection = new ReflectionClass($object);

        $frozen = [];

        foreach ($properties as $key => $value) {
            $name = is_numeric($key) ? $value : $key;

            $property = $reflection->getProperty($name);

            $property->setAccessible(true);

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
                    $property->setAccessible(true);
                    $property->setValue($object, $value);
                }
            },
        ];
    }
}
