<?php

namespace Livewire\Blaze\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Livewire\Blaze\BlazeServiceProvider;

abstract class TestCase extends Orchestra
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
        $app['config']->set('database.default', 'testing');
        $app['config']->set('view.paths', [__DIR__.'/fixtures/views']);
        $app['config']->set('view.compiled', __DIR__.'/fixtures/compiled');
        
        $app['config']->set('blaze', [
            'enabled' => true,
            'cache' => [
                'enabled' => true,
                'driver' => 'array',
                'ttl' => 3600,
                'prefix' => 'blaze_test_',
            ],
            'optimization' => [
                'inline_components' => true,
                'precompile_components' => true,
                'lazy_load_components' => true,
                'minify_output' => false,
                'component_caching' => true,
                'slot_optimization' => true,
            ],
            'monitoring' => [
                'enabled' => true,
                'log_performance' => false,
                'threshold_ms' => 100,
            ],
            'components' => [
                'auto_discover' => true,
                'paths' => [
                    __DIR__.'/fixtures/views/components',
                ],
                'exclude' => [],
            ],
            'compile' => [
                'path' => __DIR__.'/fixtures/blaze',
                'manifest' => __DIR__.'/fixtures/blaze/manifest.json',
            ],
        ]);
    }
}