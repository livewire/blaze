<?php

namespace Livewire\Blaze;

use Livewire\Blaze\Compiler\Wrapper;
use Livewire\Blaze\Compiler\Compiler;
use Livewire\Blaze\Runtime\BlazeRuntime;
use Livewire\Blaze\Walker\Walker;
use Livewire\Blaze\Tokenizer\Tokenizer;
use Livewire\Blaze\Parser\Parser;
use Livewire\Blaze\Memoizer\Memoizer;
use Livewire\Blaze\Folder\Folder;
use Livewire\Blaze\Directive\BlazeDirective;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;

class BlazeServiceProvider extends ServiceProvider
{
    /** {@inheritdoc} */
    public function register(): void
    {
        $this->registerBlazeManager();
        $this->registerBlazeRuntime();
        $this->registerBlazeDirectives();
        $this->registerBladeMacros();
        $this->interceptBladeCompilation();
        $this->interceptViewCacheInvalidation();
    }

    /**
     * Register the BlazeManager singleton and its aliases.
     */
    protected function registerBlazeManager(): void
    {
        $this->app->singleton(BlazeRuntime::class, fn () => new BlazeRuntime);
        $this->app->singleton(Config::class, fn () => new Config);

        $config = $this->app->make(Config::class);

        $this->app->singleton(BlazeManager::class, fn () => new BlazeManager(
            new Tokenizer,
            new Parser,
            new Walker,
            new Compiler($config),
            new Folder($config),
            new Memoizer($config),
            new Wrapper,
            $this->app->make(Config::class),
        ));

        $this->app->alias(BlazeManager::class, Blaze::class);

        $this->app->bind('blaze', fn ($app) => $app->make(BlazeManager::class));
        $this->app->bind('blaze.runtime', fn ($app) => $app->make(BlazeRuntime::class));
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
        $this->app->make('view')->macro('pushConsumableComponentData', function ($data) {
            $this->componentStack[] = new \Illuminate\Support\HtmlString('');

            $this->componentData[$this->currentComponent()] = $data;
        });

        $this->app->make('view')->macro('popConsumableComponentData', function () {
            array_pop($this->componentStack);
        });
    }

    /**
     * Hook into Blade's pre-compilation phase to run the Blaze pipeline.
     */
    protected function interceptBladeCompilation(): void
    {
        $blaze = app(BlazeManager::class);

        BladeService::earliestPreCompilationHook(function ($input) use ($blaze) {
            if ($blaze->isDisabled()) return $input;

            if (BladeService::containsLaravelExceptionView($input)) return $input;

            return $blaze->collectAndAppendFrontMatter($input, function ($input) use ($blaze) {
                return $blaze->compile($input);
            });
        });
    }

    /**
     * Recompile views when folded component dependencies have changed.
     */
    protected function interceptViewCacheInvalidation(): void
    {
        $blaze = app(BlazeManager::class);

        BladeService::viewCacheInvalidationHook(function ($view, $invalidate) use ($blaze) {
            if ($blaze->isDisabled()) return;

            if ($blaze->viewContainsExpiredFrontMatter($view)) {
                $invalidate();
            }
        });
    }

    /** {@inheritdoc} */
    public function boot(): void
    {
        //
    }
}
