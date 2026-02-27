<?php

namespace Livewire\Blaze;

use Livewire\Blaze\Compiler\Profiler;
use Livewire\Blaze\Runtime\BlazeRuntime;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;

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
     * Share the BlazeRuntime instance with all views.
     */
    protected function registerBlazeRuntime(): void
    {
        View::share('__blaze', $this->app->make(BlazeRuntime::class));
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
        BladeService::earliestPreCompilationHook(function ($input) {
            if (BladeService::containsLaravelExceptionView($input)) {
                return $input;
            }

            if (Blaze::isDisabled()) {
                if (Blaze::isDebugging()) {
                    return Blaze::compileForDebug($input);
                }

                return $input;
            }

            return Blaze::collectAndAppendFrontMatter($input, function ($input) {
                return Blaze::compile($input);
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
        if (Blaze::isDebugging()) {
            $prefix = config('blaze.route_prefix', '');
            DebuggerMiddleware::register($prefix);
        }
    }
}
