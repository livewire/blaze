<?php

namespace Livewire\Blaze;

use Illuminate\Support\ServiceProvider;
use Livewire\Blaze\Compiler\Compiler;
use Livewire\Blaze\Parser\Parser;

class BlazeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BlazeManager::class, fn () => new BlazeManager(
            new Compiler,
            new Parser,
        ));

        $this->app->alias(BlazeManager::class, Blaze::class);

        $this->app->bind('blaze', fn ($app) => $app->make(BlazeManager::class));
    }

    public function boot(): void
    {
        // Bootstrap services
    }
}