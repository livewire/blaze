<?php

namespace Livewire\Blaze;

use Livewire\Blaze\Compiler\ComponentCompiler;
use Livewire\Blaze\Compiler\TagCompiler;
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
    public function register(): void
    {
        $this->registerBlazeManager();
        $this->registerBlazeRuntime();
        $this->registerBlazeDirectiveFallbacks();
        $this->registerBladeMacros();
        $this->interceptBladeCompilation();
        $this->interceptViewCacheInvalidation();
    }

    protected function registerBlazeManager(): void
    {
        $this->app->singleton(BlazeRuntime::class, fn () => new BlazeRuntime);
        $this->app->singleton(BlazeConfig::class, fn () => new BlazeConfig);

        $config = $this->app->make(BlazeConfig::class);

        $this->app->singleton(BlazeManager::class, fn () => new BlazeManager(
            new Tokenizer,
            new Parser,
            new Walker,
            new TagCompiler($config),
            new Folder($config),
            new Memoizer($config),
            new ComponentCompiler,
            $config,
        ));

        $this->app->alias(BlazeManager::class, Blaze::class);

        $this->app->bind('blaze', fn ($app) => $app->make(BlazeManager::class));
        $this->app->bind('blaze.runtime', fn ($app) => $app->make(BlazeRuntime::class));
    }

    protected function registerBlazeRuntime(): void
    {
        View::share('__blaze', $this->app->make(BlazeRuntime::class));
    }

    protected function registerBlazeDirectiveFallbacks(): void
    {
        Blade::directive('unblaze', function ($expression) {
            return ''
                . '<'.'?php $__getScope = fn($scope = []) => $scope; ?>'
                . '<'.'?php if (isset($scope)) $__scope = $scope; ?>'
                . '<'.'?php $scope = $__getScope('.$expression.'); ?>';
        });

        Blade::directive('endunblaze', function () {
            return '<'.'?php if (isset($__scope)) { $scope = $__scope; unset($__scope); } ?>';
        });

        BlazeDirective::registerFallback();
    }

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

    public function boot(): void
    {
        // Bootstrap services
    }
}
