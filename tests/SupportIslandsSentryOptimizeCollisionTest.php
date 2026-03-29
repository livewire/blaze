<?php

use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\File;
use Illuminate\View\Compilers\BladeCompiler;

/**
 * Regression: when Sentry resolves/decorates the Blade engine during `php artisan optimize`,
 * Laravel can end up compiling views with one BladeCompiler instance while the Blade facade
 * points at a different instance.
 *
 * Livewire islands use `Blade::getPath()` as a signature; when it returns null they fall back
 * to `crc32($content)`, which can collide across different views and cause island cache
 * overwrites and scope bleed.
 */

test('livewire islands do not collapse when blade facade compiler differs from the compiling instance (sentry/optimize)', function () {
    $originalStoragePath = app()->storagePath();

    // Isolate storage so we can inspect livewire island cache files deterministically.
    $storage = sys_get_temp_dir() . '/blaze-islands-' . uniqid();
    File::ensureDirectoryExists($storage);

    try {
        app()->useStoragePath($storage);

        // Livewire must be registered so its inline-island precompiler is present.
        app()->register(\Livewire\LivewireServiceProvider::class);

        /** @var BladeCompiler $compilerA */
        $compilerA = app('blade.compiler');

        // In a normal app bootstrap Livewire registers its prepareStrings precompiler
        // before Blaze adds its own "earliest" hook (Blaze uses app()->booted()).
        // In this Testbench suite, we register Livewire late (after boot) so we need
        // to reorder the callbacks to match real-world ordering.
        $reflection = new \ReflectionClass($compilerA);
        $property = $reflection->getProperty('prepareStringsForCompilationUsing');
        $property->setAccessible(true);
        $callbacks = $property->getValue($compilerA);

        if (count($callbacks) >= 2) {
            $last = array_pop($callbacks);
            array_unshift($callbacks, $last);
            $property->setValue($compilerA, $callbacks);
        }

        // Mimic Sentry: resolve the blade engine early, capturing compilerA inside the engine resolver.
        // Later, the container will report a different blade.compiler (compilerB), but compilation
        // still happens through compilerA.
        app('view.engine.resolver')->resolve('blade');

        // Swap the container's blade.compiler to a fresh instance to mimic the optimize/config:cache swap.
        $compilerB = new BladeCompiler(app('files'), app('config')->get('view.compiled'));
        app()->instance('blade.compiler', $compilerB);
        Facade::clearResolvedInstance('blade.compiler');

        $pathA = fixture_path('views/livewire/repro-a.blade.php');
        $pathB = fixture_path('views/livewire/repro-b.blade.php');

        $contentsA = file_get_contents($pathA);
        $contentsB = file_get_contents($pathB);

        // Sanity: both views contain inline islands.
        expect($contentsA)->toContain('@endisland');
        expect($contentsB)->toContain('@endisland');

        // Precondition: if Blade::getPath() is null, Livewire falls back to crc32($content)
        // and hashes it using substr(md5($signature), 0, 8). These fixtures are intentionally
        // padded so the truncated hash collides across the two different templates.
        $sigA = sprintf('%u', crc32($contentsA));
        $sigB = sprintf('%u', crc32($contentsB));
        expect(substr(md5($sigA), 0, 8))->toBe(substr(md5($sigB), 0, 8));

        // Compile both views through compilerA (the one that has the Livewire precompiler registered).
        $compilerA->compile($pathA);
        $compilerA->compile($pathB);

        $islandsDir = storage_path('framework/views/livewire/islands');

        $files = collect(File::glob($islandsDir . '/*.blade.php'))
            ->map(fn($f) => basename($f))
            ->values();

        $allIslandContents = $files
            ->map(fn($name) => file_get_contents($islandsDir . '/' . $name))
            ->join("\n\n");

        // Both islands should exist as separate cached files.
        expect($files)->toHaveCount(2);
        expect($allIslandContents)->toContain('data-repro-marker="A-ISLAND"');
        expect($allIslandContents)->toContain('data-repro-marker="B-ISLAND"');
    } finally {
        app()->useStoragePath($originalStoragePath);
        File::deleteDirectory($storage);
    }
});
