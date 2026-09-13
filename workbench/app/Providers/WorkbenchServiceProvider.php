<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Blaze\Blaze;

class WorkbenchServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Blaze::optimize()->in(resource_path('views/components/bench/blaze'));
    }
}
