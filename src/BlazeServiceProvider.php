<?php

namespace Livewire\Blaze;

use Livewire\Blaze\Compiler\Profiler;
use Livewire\Blaze\Runtime\BlazeRuntime;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\View\Engines\CompilerEngine;
use function is_object;
use function method_exists;
use function spl_object_id;

class BlazeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerConfig();

        $this->app->singleton(BlazeRuntime::class);
        $this->app->singleton(Config::class);
        $this->app->singleton(Debugger::class);
        $this->app->singleton(Profiler::class);
        $this->app->singleton(BlazeManager::class);

        $this->app->alias(BlazeManager::class, Blaze::class);

        $this->app->alias(BlazeManager::class, 'blaze');
        $this->app->alias(BlazeRuntime::class, 'blaze.runtime');
        $this->app->alias(Config::class, 'blaze.config');
        $this->app->alias(Debugger::class, 'blaze.debugger');
    }

    protected function registerConfig(): void
    {
        $config = __DIR__.'/../config/blaze.php';

        $this->publishes([$config => base_path('config/blaze.php')], ['blaze', 'blaze:config']);

        $this->mergeConfigFrom($config, 'blaze');
    }

    public function boot(): void
    {
        $this->registerBlazeDirectives();
        $this->registerBlazeRuntime();
        $this->registerBladeMacros();
        $this->interceptViewCacheInvalidation();
        $this->interceptBladeCompilation();
        $this->registerDebuggerMiddleware();
    }

    /**
     * Make the BlazeRuntime instance available to Blade views.
     */
    protected function registerBlazeRuntime(): void
    {
        View::composer('*', function (\Illuminate\View\View $view) {
            if (Blaze::isDisabled() && ! Blaze::isDebugging()) {
                return;
            }

            // Avoid injecting the BlazeRuntime into non-Blade views (like Statamic's Antlers)
            if ($this->resolveCompilerEngine($view->getEngine()) instanceof CompilerEngine) {
                $view->with('__blaze', $this->app->make(BlazeRuntime::class));
            }
        });
    }

    protected function resolveCompilerEngine($engine): ?CompilerEngine
    {
        $seen = [];

        while (true) {
            if ($engine instanceof CompilerEngine) {
                return $engine;
            }

            if (! is_object($engine) || ! method_exists($engine, 'getEngine')) {
                return null;
            }

            $objectId = spl_object_id($engine);

            if (isset($seen[$objectId])) {
                return null;
            }

            $seen[$objectId] = true;
            $engine = $engine->getEngine();
        }
    }

    /**
     * Register @blaze, @unblaze, and @endunblaze Blade directives.
     */
    protected function registerBlazeDirectives(): void
    {
        Blade::directive('blaze', function () {
            return '';
        });

        Blade::directive('unblaze', function ($expression) {
            return ''
                . '<'.'?php $__getScope = fn($scope = []) => $scope; ?>'
                . '<'.'?php if (isset($scope)) $__scope = $scope; ?>'
                . '<'.'?php $scope = $__getScope('.$expression.'); ?>';
        });

        Blade::directive('endunblaze', function () {
            return '<'.'?php if (isset($__scope)) { $scope = $__scope; unset($__scope); } ?>';
        });
    }

    /**
     * Register view factory macros for consumable component data (@aware support).
     */
    protected function registerBladeMacros(): void
    {
        View::macro('pushConsumableComponentData', function ($data) {
            /** @var \Illuminate\View\Factory $this */
            $this->componentStack[] = new \Illuminate\Support\HtmlString('');
            $this->componentData[$this->currentComponent()] = $data;
        });

        View::macro('popConsumableComponentData', function () {
            /** @var \Illuminate\View\Factory $this */
            array_pop($this->componentStack);
        });
    }

    /**
     * Hook into Blade's pre-compilation phase to run the Blaze pipeline.
     */
    protected function interceptBladeCompilation(): void
    {
        BladeService::earliestPreCompilationHook(function ($input, $path) {
            if (BladeService::containsLaravelExceptionView($input)) {
                return $input;
            }

            if (Blaze::isDisabled()) {
                if (Blaze::isDebugging()) {
                    return Blaze::compileForDebug($input, $path);
                }

                return $input;
            }

            return Blaze::collectAndAppendFrontMatter($input, function ($input) use ($path) {
                return Blaze::compile($input, $path);
            });
        });
    }

    /**
     * Recompile views when folded component dependencies have changed.
     */
    protected function interceptViewCacheInvalidation(): void
    {
        BladeService::viewCacheInvalidationHook(function ($view, $invalidate) {
            if (Blaze::isDisabled()) {
                return;
            }

            if (Blaze::viewContainsExpiredFrontMatter($view)) {
                $invalidate();
            }
        });
    }

    /**
     * Register the Debugger middleware.
     */
    protected function registerDebuggerMiddleware(): void
    {
        $this->app->booted(function () {
            if (Blaze::isDebugging()) {
                DebuggerMiddleware::register();
            }
        });
    }
}
