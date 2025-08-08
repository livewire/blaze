<?php

namespace Livewire\Blaze\Tests;

use Livewire\Blaze\BlazeServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app)
    {
        return [
            BlazeServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Setup default configuration
        $app['config']->set('view.paths', [
            __DIR__ . '/fixtures/views',
        ]);

        $app['config']->set('view.compiled', sys_get_temp_dir() . '/views');
    }
}