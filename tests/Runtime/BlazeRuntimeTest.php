<?php

use Illuminate\Support\Facades\File;
use Livewire\Blaze\BladeService;
use Livewire\Blaze\Runtime\BlazeRuntime;
use Livewire\Blaze\Support\Utils;

test('uses blaze compiled path for runtime and temporary cache', function () {
    $customPath = config('view.compiled').'/blaze_test_'.uniqid();
    config()->set('blaze.compiled_path', $customPath);

    $runtime = new BlazeRuntime();

    expect($runtime->compiledPath)->toBe($customPath);
    expect(BladeService::getTemporaryCachePath())->toBe($customPath.'/temp');

    File::deleteDirectory($customPath);
});

test('compiles components into blaze compiled path when customized', function () {
    $customPath = config('view.compiled').'/blaze_test_'.uniqid();
    config()->set('blaze.compiled_path', $customPath);

    $runtime = new BlazeRuntime();

    $path = fixture_path('components/input.blade.php');
    $hash = Utils::hash($path);

    $customCompiledPath = $customPath.'/'.$hash.'.php';
    $defaultCompiledPath = config('view.compiled').'/'.$hash.'.php';

    File::delete($customCompiledPath);
    File::delete($defaultCompiledPath);
    File::deleteDirectory($customPath);

    $runtime->ensureCompiled($path, $customCompiledPath);

    expect(File::exists($customCompiledPath))->toBeTrue();
    expect(File::exists($defaultCompiledPath))->toBeFalse();

    File::deleteDirectory($customPath);
});
