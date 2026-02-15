<?php

use Livewire\Blaze\Config;

test('returns parameters', function () {
    $config = new Config;

    $config->in(fixture_path('views/components'), compile: true, memo: true, fold: true);
    $config->in(fixture_path('views/components/ui')); // Defaults to compile: true

    expect($config->shouldCompile(fixture_path('views/components/button.blade.php')))->toBeTrue();
    expect($config->shouldMemoize(fixture_path('views/components/button.blade.php')))->toBeTrue();
    expect($config->shouldFold(fixture_path('views/components/button.blade.php')))->toBeTrue();

    expect($config->shouldCompile(fixture_path('views/components/ui/button.blade.php')))->toBeTrue();
    expect($config->shouldMemoize(fixture_path('views/components/ui/button.blade.php')))->toBeFalse();
    expect($config->shouldFold(fixture_path('views/components/ui/button.blade.php')))->toBeFalse();
});

test('resolves by most specific path', function () {
    $config = new Config;

    $config->in(fixture_path('views/components'), fold: true);
    $config->in(fixture_path('views/components/ui'), fold: false);

    expect($config->shouldFold(fixture_path('views/components/button.blade.php')))->toBeTrue();
    expect($config->shouldFold(fixture_path('views/components/ui/button.blade.php')))->toBeFalse();
});

test('handles conflicting paths', function () {
    $config = new Config;

    $config->in(fixture_path('views/components'), fold: true);

    expect($config->shouldCompile(fixture_path('views/components_legacy/button.blade.php')))->toBeFalse();
});

test('handles trailing slashes', function () {
    $config = new Config;

    $config->in(fixture_path('views/components/'), fold: true);

    expect($config->shouldFold(fixture_path('views/components/button.blade.php')))->toBeTrue();
});

test('handles nonexisting paths', function () {
    $config = new Config;

    $config->in(fixture_path('views/components'));

    expect($config->shouldCompile(fixture_path('views/components/nonexistent.blade.php')))->toBeFalse();
    expect($config->shouldMemoize(fixture_path('views/components/nonexistent.blade.php')))->toBeFalse();
    expect($config->shouldCompile(fixture_path('views/components/nonexistent.blade.php')))->toBeFalse();
});