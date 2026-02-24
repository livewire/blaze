<?php

namespace Livewire\Blaze;

use Illuminate\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\View\Compilers\ComponentTagCompiler;
use ReflectionClass;
use Livewire\Blaze\Support\Utils;

class BladeService
{
    /**
     * Cached reflection methods for the active Blade compiler by class.
     *
     * @var array<string, array<string, \ReflectionMethod>>
     */
    protected static $bladeCompilerMethods = [];

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
                $result = '';
                
                $template = $this->storeUncompiledBlocks($template);
                $template = $this->compileComments($template);

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

                $result = $this->restoreRawContent($result);

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

        [$compiler, $restore] = static::freezeObjectProperties($compiler, [
            'cachePath' => $temporaryCachePath,
            'rawBlocks',
            'prepareStringsForCompilationUsing' => [
                function ($input) {
                    if (Unblaze::hasUnblaze($input)) {
                        $input = Unblaze::processUnblazeDirectives($input);
                    };


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
            'dataStack' => [],
            'slotsStack' => [],
        ]);

        try {
            Blaze::startFolding();

            $result = $compiler->render($template, deleteCachedView: true);
        } finally {
            $restore();
            $restoreFactory();
            $restoreRuntime();

            Blaze::stopFolding();
        }

        $result = Unblaze::replaceUnblazePrecompiledDirectives($result);

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
        $app = app();

        $app->booted(function () use ($app, $callback) {
            $app->make('blade.compiler')->prepareStringsForCompilationUsing(function ($input) use ($app, $callback) {
                return static::inAppContext($app, fn () => $callback($input));
            });
        });
    }

    /**
     * Execute a callback while forcing a specific app/container context.
     */
    protected static function inAppContext(Application $app, callable $callback): mixed
    {
        $previousContainer = Container::getInstance();
        $previousFacadeApp = Facade::getFacadeApplication();

        if ($previousContainer === $app && $previousFacadeApp === $app) {
            return $callback();
        }

        Container::setInstance($app);
        Facade::setFacadeApplication($app);

        try {
            return $callback();
        } finally {
            Container::setInstance($previousContainer);
            Facade::setFacadeApplication($previousFacadeApp);
        }
    }

    /**
     * Invoke the Blade compiler's storeUncompiledBlocks via reflection.
     */
    public static function preStoreUncompiledBlocks(string $input): string
    {
        return static::callBladeCompilerMethod('storeUncompiledBlocks', $input);
    }

    /**
     * Store only @verbatim blocks as raw block placeholders.
     */
    public static function storeVerbatimBlocks(string $input): string
    {
        return static::callBladeCompilerMethod('storeVerbatimBlocks', $input);
    }

    /**
     * Restore raw block placeholders to their original content.
     */
    public static function restoreRawBlocks(string $input): string
    {
        return static::callBladeCompilerMethod('restoreRawContent', $input);
    }

    /**
     * Invoke the Blade compiler's compileComments via reflection.
     */
    public static function compileComments(string $input): string
    {
        return static::callBladeCompilerMethod('compileComments', $input);
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
    public static function preprocessAttributeString(string $attributeString): string
    {
        $compiler = new ComponentTagCompiler(blade: app('blade.compiler'));

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

    public static function compileUseStatements(string $input): string
    {
        return static::compileDirective($input, 'use', function ($expression) {
            return static::callBladeCompilerMethod('compileUse', $expression);
        });
    }

    /**
     * Invoke a method on the current Blade compiler with cached reflection.
     */
    protected static function callBladeCompilerMethod(string $method, mixed ...$arguments): mixed
    {
        $compiler = app('blade.compiler');
        $class = $compiler::class;

        if (! isset(static::$bladeCompilerMethods[$class][$method])) {
            $reflection = new ReflectionClass($compiler);
            static::$bladeCompilerMethods[$class][$method] = $reflection->getMethod($method);
        }

        return static::$bladeCompilerMethods[$class][$method]->invoke($compiler, ...$arguments);
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
     * Strip surrounding quotes from a string using ComponentTagCompiler.
     */
    public static function stripQuotes(string $input): string
    {
        return (new ComponentTagCompiler(blade: app('blade.compiler')))->stripQuotes($input);
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
    protected static function freezeObjectProperties(object $object, array $properties)
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
