<?php

return [
    'enabled' => env('BLAZE_ENABLED', true),

    'cache' => [
        'enabled' => env('BLAZE_CACHE_ENABLED', true),
        'driver' => env('BLAZE_CACHE_DRIVER', 'file'),
        'ttl' => env('BLAZE_CACHE_TTL', 3600),
        'prefix' => env('BLAZE_CACHE_PREFIX', 'blaze_'),
    ],

    'optimization' => [
        'inline_components' => true,
        'precompile_components' => true,
        'lazy_load_components' => true,
        'minify_output' => env('BLAZE_MINIFY', false),
        'component_caching' => true,
        'slot_optimization' => true,
    ],

    'monitoring' => [
        'enabled' => env('BLAZE_MONITORING', false),
        'log_performance' => false,
        'threshold_ms' => 100,
    ],

    'components' => [
        'auto_discover' => true,
        'paths' => [
            resource_path('views/components'),
        ],
        'exclude' => [],
    ],

    'compile' => [
        'path' => storage_path('framework/blaze'),
        'manifest' => storage_path('framework/blaze/manifest.json'),
    ],
];