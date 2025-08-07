<?php

namespace Livewire\Blaze;

use Livewire\Blaze\Walker\Walker;
use Livewire\Blaze\Tokenizer\Tokenizer;
use Livewire\Blaze\Renderer\Renderer;
use Livewire\Blaze\Parser\Parser;
use Livewire\Blaze\Folder\Folder;
use Illuminate\Support\ServiceProvider;

class BlazeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BlazeManager::class, fn () => new BlazeManager(
            $tokenizer = new Tokenizer,
            $parser = new Parser,
            $renderer = new Renderer,
            $walker = new Walker,
            $folder = new Folder(
                fn ($blade) => (new BladeHacker)->render($blade),
                fn ($node) => $renderer->renderNode($node),
            ),
        ));

        $this->app->alias(BlazeManager::class, Blaze::class);

        $this->app->bind('blaze', fn ($app) => $app->make(BlazeManager::class));
    }

    public function boot(): void
    {
        // Bootstrap services
    }
}