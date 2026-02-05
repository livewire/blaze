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

        // Isolate compiled view paths for parallel testing to prevent
        // processes from clearing each other's compiled views.
        if ($token = $_SERVER['TEST_TOKEN'] ?? null) {
            $basePath = $app['config']->get('view.compiled');
            $app['config']->set('view.compiled', $basePath . '/test_' . $token);
        }
    }
}