<?php

use Livewire\Blaze\Support\Utils;
use Livewire\Blaze\Compiler\Wrapper;

test('wraps component templates into function definitions', function () {
    $path = fixture_path('components/input.blade.php');
    $source = file_get_contents($path);
    $hash = Utils::hash($path);

    $wrapped = app(Wrapper::class)->wrap($source, $path, $source);

    expect($wrapped)->toEqualCollapsingWhitespace(join('', [
        '<?php if (!function_exists(\'_'. $hash .'\')): function _'. $hash .'($__blaze, $__data = [], $__slots = [], $__bound = [], $__this = null) { ',
        '$__env = $__blaze->env; ',
        'if (($__data[\'attributes\'] ?? null) instanceof \Illuminate\View\ComponentAttributeBag) { ',
        '$__data = $__data + $__data[\'attributes\']->all(); unset($__data[\'attributes\']); ',
        '} ',
        'extract($__slots, EXTR_SKIP); unset($__slots); ',
        '$attributes = new \Livewire\Blaze\Runtime\BlazeAttributeBag($__data); ?><?php ',
        '$__data = array_intersect_key($__data, $attributes->getAttributes()); ',
        '$__defaults = [\'type\' => \'text\', \'disabled\' => false]; ',
        '$type ??= $__data[\'type\'] ?? $__defaults[\'type\']; unset($__data[\'type\']); ',
        '$disabled ??= $__data[\'disabled\'] ?? $__defaults[\'disabled\']; unset($__data[\'disabled\']); ',
        'unset($__defaults); ',
        '$attributes = \Livewire\Blaze\Runtime\BlazeAttributeBag::sanitized($__data, $__bound); unset($__data, $__bound); ?> ',
        '<input {{ $attributes }} type="{{ $type }}" @if ($disabled) disabled @endif >',
        '<?php } endif; ?>',
    ]));
});

test('injects errors when source uses dollar sign errors', function () {
    $path = fixture_path('components/compilable/input-errors.blade.php');
    $source = file_get_contents($path);

    $wrapped = app(Wrapper::class)->wrap($source, $path, $source);

    expect($wrapped)->toContain('$errors = $__blaze->errors;');
});

test('injects errors when source uses errors directive', function () {
    $path = fixture_path('components/compilable/input-errors-directive.blade.php');
    $source = file_get_contents($path);

    $wrapped = app(Wrapper::class)->wrap($source, $path, $source);

    expect($wrapped)->toContain('$errors = $__blaze->errors;');
});
