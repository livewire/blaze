<?php

use Illuminate\Support\Facades\View;

beforeEach(function () {
    // Register anonymous components used by the benchmark
    app('blade.compiler')->anonymousComponentPath(__DIR__ . '/fixtures/components');
});

function render($viewFile, $enabled = false) {
    clearCache();

    // Warm up the compiler/cache...
    View::file($viewFile)->render();

    // Run the benchmark...
    $iterations = 1; // single run of a 25k loop inside the view

    $start = microtime(true);

    for ($i = 0; $i < $iterations; $i++) {
        View::file($viewFile)->render();
    }

    $duration = (microtime(true) - $start) * 1000; // ms

    return $duration;
}

function clearCache() {
    $files = glob(__DIR__ . '/../vendor/orchestra/testbench-core/laravel/storage/framework/views/*');
    foreach ($files as $file) {
        if (!str_ends_with($file, '.gitignore')) {
           if (is_dir($file)) {
                // Recursively remove directory contents
                array_map('unlink', glob("$file/*.*"));
                rmdir($file);
           } else {
                unlink($file);
            }
        }
    }
}

it('can run performance benchmarks', function () {
    app('blaze')->enable();

    $viewFile = __DIR__ . '/fixtures/benchmark/simple-button-in-loop.blade.php';
    $duration = render($viewFile, enabled: false);
    fwrite(STDOUT, "Blaze enabled - render 25k component loop: " . number_format($duration, 2) . " ms\n");

    app('blaze')->disable();

    $viewFile = __DIR__ . '/fixtures/benchmark/simple-button-in-loop.blade.php';
    $duration = render($viewFile, enabled: false);
    fwrite(STDOUT, "Blaze disabled - render 25k component loop: " . number_format($duration, 2) . " ms\n");

    app('blaze')->enable();

    $viewFile = __DIR__ . '/fixtures/benchmark/no-fold-button-in-loop.blade.php';
    $duration = render($viewFile, enabled: false);
    fwrite(STDOUT, "Blaze enabled but no folding - render 25k component loop: " . number_format($duration, 2) . " ms\n");

    expect(true)->toBeTrue();
});
