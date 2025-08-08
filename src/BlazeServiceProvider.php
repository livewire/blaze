<?php

namespace Livewire\Blaze;

use Livewire\Blaze\Walker\Walker;
use Livewire\Blaze\Tokenizer\Tokenizer;
use Livewire\Blaze\Renderer\Renderer;
use Livewire\Blaze\Parser\Parser;
use Livewire\Blaze\Inspector\Inspector;
use Livewire\Blaze\Folder\Folder;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;

class BlazeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BlazeManager::class, fn () => new BlazeManager(
            new Tokenizer,
            new Parser,
            new Renderer,
            new Walker,
            new Inspector,
            new Folder(
                renderBlade: fn ($blade) => (new BladeHacker)->render($blade),
                renderNodes: fn ($nodes) => (new Renderer)->render($nodes),
                componentNameToPath: fn ($name) => (new BladeHacker)->componentNameToPath($name),
            ),
        ));

        $this->app->alias(BlazeManager::class, Blaze::class);

        $this->app->bind('blaze', fn ($app) => $app->make(BlazeManager::class));

        Blade::directive('pure', fn () => '');

        (new BladeHacker)->earliestPreCompilationHook(function ($input) {
            if ((new BladeHacker)->containsLaravelExceptionView($input)) return $input;

            app('blaze')->collectAndAppendFrontMatter($input, function ($input) {
                return app('blaze')->compile($input);
            });
        });

        (new BladeHacker)->viewCacheInvalidationHook(function ($view, $invalidate) {
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