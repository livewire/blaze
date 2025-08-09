<?php

namespace Livewire\Blaze;

use Livewire\Blaze\Tokenizer\Tokenizer;
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
        $bladeService = new BladeService;
        $renderer = new Renderer;

        $this->app->singleton(BlazeManager::class, fn () => new BlazeManager(
            new Tokenizer,
            new Parser,
            $renderer,
            new Walker,
            new Folder(
                renderBlade: fn ($blade) => $bladeService->isolatedRender($blade),
                renderNodes: fn ($nodes) => $renderer->render($nodes),
                componentNameToPath: fn ($name) => $bladeService->componentNameToPath($name),
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
        $blaze = app(BlazeManager::class);

        (new BladeService)->earliestPreCompilationHook(function ($input) use ($blaze) {
            if ($blaze->isDisabled()) return $input;

            if ((new BladeService)->containsLaravelExceptionView($input)) return $input;

            return $blaze->collectAndAppendFrontMatter($input, function ($input) use ($blaze) {
                return $blaze->compile($input);
            });
        });
    }

    protected function interceptViewCacheInvalidation(): void
    {
        $blaze = app(BlazeManager::class);

        (new BladeService)->viewCacheInvalidationHook(function ($view, $invalidate) use ($blaze) {
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