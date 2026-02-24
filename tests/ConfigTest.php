<?php

use Illuminate\Support\Facades\File;
use Livewire\Blaze\Config;

test('re-evaluates decisions when optimization rules change', function () {
    $root = sys_get_temp_dir() . '/blaze-config-' . str_replace('.', '', uniqid('', true));
    $components = $root . '/components';
    $nested = $components . '/admin';
    $file = $nested . '/panel.blade.php';

    File::ensureDirectoryExists($nested);
    File::put($file, '<div />');

    try {
        $config = new Config;

        $config->add($components, true);
        expect($config->shouldCompile($file))->toBeTrue();

        // Updating rules must invalidate memoized per-file decisions.
        $config->add($nested, false);
        expect($config->shouldCompile($file))->toBeFalse();

        $config->add($nested, true);
        expect($config->shouldCompile($file))->toBeTrue();
    } finally {
        File::deleteDirectory($root);
    }
});

test('supports config paths created after registration', function () {
    $root = sys_get_temp_dir() . '/blaze-config-' . str_replace('.', '', uniqid('', true));
    $futureDir = $root . '/future-components';
    $futureFile = $futureDir . '/fresh.blade.php';

    File::ensureDirectoryExists($root);

    try {
        $config = new Config;
        $config->add($futureDir, true);

        expect($config->shouldCompile($futureFile))->toBeFalse();

        File::ensureDirectoryExists($futureDir);
        File::put($futureFile, '<div />');

        expect($config->shouldCompile($futureFile))->toBeTrue();
    } finally {
        File::deleteDirectory($root);
    }
});
