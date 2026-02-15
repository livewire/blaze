<?php

use Livewire\Blaze\Support\Utils;

/*
|--------------------------------------------------------------------------
| Fold Fallback
|--------------------------------------------------------------------------
| Components with @blaze(fold: true) attempt compile-time rendering.
| When that fails (e.g., dynamic expressions that can't be evaluated),
| they fall back to function compilation instead of breaking.
*/

test('static content folds successfully', function () {
    $compiled = app('blaze')->compile('<x-fold-fallback.fold-only />');

    expect($compiled)
        ->toContain('Static content that should fold')
        ->not->toContain('<x-fold-fallback.fold-only')
        ->not->toContain('$__blaze->ensureCompiled');
});

test('folded content renders at runtime', function () {
    $result = blade(
        view: '<x-wrapper />',
        components: ['wrapper' => '@blaze
<div class="wrapper">
    <x-fold-fallback.fold-only />
</div>'],
    );

    expect($result)
        ->toContain('<div class="wrapper">')
        ->toContain('Static content that should fold');
});

test('falls back to function compilation when folding fails', function () {
    $componentPath = fixture_path('components/fold-fallback/will-fail-fold.blade.php');
    $hash = Utils::hash($componentPath);

    $compiled = app('blaze')->compile('<x-fold-fallback.will-fail-fold :value="$jsonString" />');

    expect($compiled)
        ->not->toContain('<x-fold-fallback.will-fail-fold')
        ->toContain('$__blaze->ensureCompiled')
        ->toContain("_{$hash}")
        ->toContain('require_once');
});

test('fold succeeds with static value that would fail dynamically', function () {
    $compiled = app('blaze')->compile('<x-fold-fallback.will-fail-fold value=\'{"key":"hello"}\' />');

    expect($compiled)
        ->toContain('Computed: hello')
        ->not->toContain('$__blaze->ensureCompiled');
});
