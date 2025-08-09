<?php

namespace Livewire\Blaze;

use Livewire\Blaze\Tokenizer\Tokenizer;
use Livewire\Blaze\Inspector\Inspector;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use Livewire\Blaze\Renderer\Renderer;
use Livewire\Blaze\Walker\Walker;
use Livewire\Blaze\Parser\Parser;
use Livewire\Blaze\Folder\Folder;

class BlazeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerBlazeManager();
        $this->registerPureDirectiveFallback();
        $this->interceptBladeCompilation();
        $this->interceptViewCacheInvalidation();
    }

    protected function registerBlazeManager(): void
    {
        $this->app->singleton(BlazeManager::class, fn () => new BlazeManager(
            new Tokenizer,
            new Parser,
            new Renderer,
            new Walker,
            new Inspector,
            new Folder(
                renderBlade: fn ($blade) => (new BladeService)->isolatedRender($blade),
                renderNodes: fn ($nodes) => (new Renderer)->render($nodes),
                componentNameToPath: fn ($name) => (new BladeService)->componentNameToPath($name),
            ),
        ));

        $this->app->alias(BlazeManager::class, Blaze::class);

        $this->app->bind('blaze', fn ($app) => $app->make(BlazeManager::class));
    }

    protected function registerPureDirectiveFallback(): void
    {
        Blade::directive('pure', fn () => '');
    }

    protected function interceptBladeCompilation(): void
    {
        (new BladeService)->earliestPreCompilationHook(function ($input) {
            if (app('blaze')->isDisabled()) return $input;

            if ((new BladeService)->containsLaravelExceptionView($input)) return $input;

            return app('blaze')->collectAndAppendFrontMatter($input, function ($input) {
                return app('blaze')->compile($input);
            });
        });
    }

    protected function interceptViewCacheInvalidation(): void
    {
        (new BladeService)->viewCacheInvalidationHook(function ($view, $invalidate) {
            if (app('blaze')->isDisabled()) return;

            if (app('blaze')->viewContainsExpiredFrontMatter($view)) {
                $invalidate();
            }
        });
    }

    public function boot(): void
    {
        // Bootstrap services
    }
}