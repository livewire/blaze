<?php

use Illuminate\Support\Facades\Blade;
use Livewire\Blaze\Runtime\BlazeRuntime;

test('ignores verbatim blocks', function () {
    $input = '@verbatim<x-input />@endverbatim';

    expect(Blade::render($input))->toBe('<x-input />');
});

test('ignores php directives', function () {
    $input = "@php echo '<x-input />'; @endphp";

    expect(Blade::render($input))->toBe('<x-input />');
});

test('ignores comments', function () {
    $input = '{{-- <x-input /> --}}';

    expect(Blade::render($input))->toBe('');
});

test('BlazeRuntime uses updated compiled path after config change', function () {
    $runtime = app(BlazeRuntime::class);
    $originalPath = config('view.compiled');

    // Simulate parallel test runner updating config after boot.
    $perProcessPath = $originalPath . '/test_3';
    config(['view.compiled' => $perProcessPath]);

    expect($runtime->compiledPath)->toBe($perProcessPath);
});

test('compiledPath respects reflection overrides used by freezeObjectProperties', function () {
    $runtime = app(BlazeRuntime::class);
    $original = config('view.compiled');

    $prop = (new ReflectionClass($runtime))->getProperty('compiledPath');

    // Freeze: explicit value takes precedence over config.
    $prop->setValue($runtime, '/tmp/blaze-temp');
    expect($runtime->compiledPath)->toBe('/tmp/blaze-temp');

    // Restore: null falls back to config.
    $prop->setValue($runtime, null);
    expect($runtime->compiledPath)->toBe($original);
});

// TODO: Install PHPStan, which probably would have caught this.
test('supports php engine', function () {
    // Make sure our hooks do not break views
    // rendered using the regular php engine.
    view('php-view')->render();
})->throwsNoExceptions();