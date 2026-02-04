<?php

namespace Livewire\Blaze;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use ReflectionClass;

class BladeService
{
    public static function render(string $template): string
    {
        return (new self)->isolatedRender($template);
    }

    public static function compileDirective(string $template, string $directive, callable $callback)
    {
        // TODO: This should also handle comments and verbatim blocks...
        // We also do this in BlazeManager::compile()...
        $compiler = static::getHackedBladeCompiler();

        $compiler->directive($directive, $callback);

        return $compiler->compileStatementsMadePublic($template);
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
    
    public function getTemporaryCachePath(): string
    {
        return storage_path('framework/views/blaze');
    }

    public function isolatedRender(string $template): string
    {
        $compiler = app('blade.compiler');

        $temporaryCachePath = $this->getTemporaryCachePath();

        File::ensureDirectoryExists($temporaryCachePath);

        $factory = app('view');

        [$factory, $restoreFactory] = $this->freezeObjectProperties($factory, [
            'componentStack' => [],
            'componentData' => [],
            'currentComponentData' => [],
            'slots' => [],
            'slotStack' => [],
            'renderCount' => 0,
        ]);

        [$compiler, $restore] = $this->freezeObjectProperties($compiler, [
            'cachePath' => $temporaryCachePath,
            'rawBlocks',
            'prepareStringsForCompilationUsing' => [
                function ($input) {
                    if (Unblaze::hasUnblaze($input)) {
                        $input = Unblaze::processUnblazeDirectives($input);
                    }

                    // Run Blaze compilation (tag compiler + component compiler only, no folding)
                    $input = app('blaze')->compileForFolding($input);

                    return $input;
                },
            ],
            'path' => null,
        ]);

        [$runtime, $restoreRuntime] = $this->freezeObjectProperties(app('blaze.runtime'), [
            'compiled' => [],
            'paths' => [],
            'compiledPath' => $temporaryCachePath,
        ]);

        try {
            // As we are rendering a string, Blade will generate a view for the string in the cache directory
            // and it doesn't use the `cachePath` property. Instead it uses the config `view.compiled` path
            // to store the view. Hence why our `temporaryCachePath` won't clean this file up. To remove
            // the file, we can pass `deleteCachedView: true` to the render method...
            $result = $compiler->render($template, deleteCachedView: true);

            $result = Unblaze::replaceUnblazePrecompiledDirectives($result);
        } finally {
            $restore();
            $restoreFactory();
            $restoreRuntime();
        }

        return $result;
    }

    public function deleteTemporaryCacheDirectory()
    {
        File::deleteDirectory($this->getTemporaryCachePath());
    }

    public function containsLaravelExceptionView(string $input): bool
    {
        return str_contains($input, 'laravel-exceptions');
    }

    public function earliestPreCompilationHook(callable $callback)
    {
        app()->booted(function () use ($callback) {
            app('blade.compiler')->prepareStringsForCompilationUsing(function ($input) use ($callback) {
                $output = $callback($input);

                return $output;
            });
        });
    }

    public function preStoreUncompiledBlocks(string $input): string
    {
        $compiler = app('blade.compiler');

        $reflection = new \ReflectionClass($compiler);
        $storeVerbatimBlocks = $reflection->getMethod('storeUncompiledBlocks');
        $storeVerbatimBlocks->setAccessible(true);

        return $storeVerbatimBlocks->invoke($compiler, $input);
    }

    public function compileComments(string $input): string
    {
        $compiler = app('blade.compiler');

        $reflection = new \ReflectionClass($compiler);
        $compileComments = $reflection->getMethod('compileComments');
        $compileComments->setAccessible(true);

        return $compileComments->invoke($compiler, $input);
    }

    public function viewCacheInvalidationHook(callable $callback)
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

    public function componentNameToPath($name): string
    {
        $compiler = app('blade.compiler');
        $viewFinder = app('view.finder');

        $reflection = new \ReflectionClass($compiler);
        $pathsProperty = $reflection->getProperty('anonymousComponentPaths');
        $pathsProperty->setAccessible(true);
        $paths = $pathsProperty->getValue($compiler) ?? [];

        // Handle namespaced components...
        if (str_contains($name, '::')) {
            [$namespace, $componentName] = explode('::', $name, 2);
            $componentPath = str_replace('.', '/', $componentName);

            // Look for namespaced anonymous component...
            foreach ($paths as $pathData) {
                if (isset($pathData['prefix']) && $pathData['prefix'] === $namespace) {
                    $basePath = rtrim($pathData['path'], '/');

                    // Try direct component file first (e.g., pages::auth.login -> auth/login.blade.php)...
                    $fullPath = $basePath.'/'.$componentPath.'.blade.php';
                    if (file_exists($fullPath)) {
                        return $fullPath;
                    }

                    // Try index.blade.php (e.g., pages::auth -> auth/index.blade.php)...
                    $indexPath = $basePath.'/'.$componentPath.'/index.blade.php';
                    if (file_exists($indexPath)) {
                        return $indexPath;
                    }

                    // Try same-name file (e.g., pages::auth -> auth/auth.blade.php)...
                    $lastSegment = basename($componentPath);
                    $sameNamePath = $basePath.'/'.$componentPath.'/'.$lastSegment.'.blade.php';
                    if (file_exists($sameNamePath)) {
                        return $sameNamePath;
                    }
                }
            }

            // Fallback to regular namespaced view lookup...
            try {
                return $viewFinder->find(str_replace('::', '::components.', $name));
            } catch (\Exception $e) {
                return '';
            }
        }

        // For regular anonymous components, check the registered paths...
        $componentPath = str_replace('.', '/', $name);

        // Check each registered anonymous component path (without prefix)...
        foreach ($paths as $pathData) {
            // Only check paths without a prefix for regular anonymous components...
            if (! isset($pathData['prefix']) || $pathData['prefix'] === null) {
                $registeredPath = $pathData['path'] ?? $pathData;

                if (is_string($registeredPath)) {
                    $basePath = rtrim($registeredPath, '/');

                    // Try direct component file first (e.g., form.input -> form/input.blade.php)...
                    $fullPath = $basePath.'/'.$componentPath.'.blade.php';
                    if (file_exists($fullPath)) {
                        return $fullPath;
                    }

                    // Try index.blade.php (e.g., form -> form/index.blade.php)...
                    $indexPath = $basePath.'/'.$componentPath.'/index.blade.php';
                    if (file_exists($indexPath)) {
                        return $indexPath;
                    }

                    // Try same-name file (e.g., card -> card/card.blade.php)...
                    $lastSegment = basename($componentPath);
                    $sameNamePath = $basePath.'/'.$componentPath.'/'.$lastSegment.'.blade.php';
                    if (file_exists($sameNamePath)) {
                        return $sameNamePath;
                    }
                }
            }
        }

        // Fallback to standard components namespace...
        try {
            return $viewFinder->find("components.{$name}");
        } catch (\Exception $e) {
            return '';
        }
    }

    protected function freezeObjectProperties(object $object, array $properties)
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
