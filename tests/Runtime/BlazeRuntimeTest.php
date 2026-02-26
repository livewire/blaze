<?php

use Illuminate\Support\Facades\File;
use Livewire\Blaze\BladeService;
use Livewire\Blaze\Runtime\BlazeRuntime;
use Livewire\Blaze\Support\Utils;

test('runtime compiled path still uses Laravel view compiled path', function () {
    $runtime = new BlazeRuntime();

    expect($runtime->compiledPath)->toBe(config('view.compiled'));
});

test('uses dedicated folded view cache path for isolated rendering', function () {
    $customPath = storage_path('framework/blaze_test_'.uniqid());
    config()->set('blaze.folded_view_cache_path', $customPath);

    expect(BladeService::getTemporaryCachePath())->toBe($customPath);
    expect(str_starts_with($customPath, config('view.compiled')))->toBeFalse();

    File::deleteDirectory($customPath);
});

test('compiles components into Laravel view compiled path', function () {
    $runtime = new BlazeRuntime();

    $path = fixture_path('components/input.blade.php');
    $hash = Utils::hash($path);

    $compiledPath = config('view.compiled').'/'.$hash.'.php';
    $temporaryPath = BladeService::getTemporaryCachePath().'/'.$hash.'.php';

    File::delete($compiledPath);
    File::delete($temporaryPath);

    $runtime->ensureCompiled($path, $compiledPath);

    expect(File::exists($compiledPath))->toBeTrue();
    expect(File::exists($temporaryPath))->toBeFalse();
});
