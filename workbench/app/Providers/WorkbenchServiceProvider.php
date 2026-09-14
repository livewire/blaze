<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Blaze\Blaze;

use function Orchestra\Testbench\workbench_path;

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
        Blaze::optimize()->in(workbench_path('resources/views/components/bench/blaze'));
    }
}
