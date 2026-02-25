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
    // BlazeRuntime captures config('view.compiled') at construction time.
    // During parallel testing, Laravel's ParallelTestingServiceProvider changes
    // config('view.compiled') to a per-process path AFTER service providers boot.
    // BlazeRuntime must reflect this change, otherwise all parallel workers
    // write compiled Blaze files to the same shared directory (race condition).

    $runtime = app(BlazeRuntime::class);
    $originalPath = config('view.compiled');

    // Simulate what Laravel's parallel test runner does: change the compiled path
    // after the app has already booted (and BlazeRuntime is already constructed).
    $perProcessPath = $originalPath . '/test_3';
    config(['view.compiled' => $perProcessPath]);

    // BlazeRuntime should use the updated path, not the stale one.
    expect($runtime->compiledPath)->toBe($perProcessPath);
});

test('compiledPath respects reflection overrides used by freezeObjectProperties', function () {
    // BladeService::freezeObjectProperties uses ReflectionProperty to temporarily
    // override compiledPath during folding renders. Verify this still works.

    $runtime = app(BlazeRuntime::class);
    $original = config('view.compiled');

    $prop = (new ReflectionClass($runtime))->getProperty('compiledPath');

    // Simulate freeze: set to a temporary path
    $prop->setValue($runtime, '/tmp/blaze-temp');
    expect($runtime->compiledPath)->toBe('/tmp/blaze-temp');

    // Simulate restore: set back to null so it falls through to config
    $prop->setValue($runtime, null);
    expect($runtime->compiledPath)->toBe($original);
});

// TODO: Install PHPStan, which probably would have caught this.
test('supports php engine', function () {
    // Make sure our hooks do not break views
    // rendered using the regular php engine.
    view('php-view')->render();
})->throwsNoExceptions();