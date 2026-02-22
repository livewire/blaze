<?php

use Livewire\Blaze\Support\Utils;
use Livewire\Blaze\Compiler\Wrapper;
use Livewire\Blaze\BladeService;

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

test('injects variables', function ($source, $expected) {
    expect(app(Wrapper::class)->wrap('', '', $source))->toContain($expected);
    expect(app(Wrapper::class)->wrap($source, '', ''))->toContain($expected);
})->with([
    'errors' => ['{{ $errors->has(\'name\') }}', '$errors = $__blaze->errors;'],
    'errors directive' => ['<input @error(\'name\') invalid @enderror >', '$errors = $__blaze->errors;'],
    'livewire' => ['{{ $__livewire->id }}', '$__livewire = $__env->shared(\'__livewire\');'],
    'entangle' => ['<div x-data="{ name: @entangle(\'name\') }"></div>', '$__livewire = $__env->shared(\'__livewire\');'],
    'app' => ['{{ $app->name }}', '$app = $__blaze->app;'],
]);

test('hoists use statements', function ($statement) {
    $source = BladeService::preStoreUncompiledBlocks("{$statement}\n\n<div></div>");

    expect(app(Wrapper::class)->wrap($source, '', $source))->toStartWith('<?php use App\Models\User; ?>');
})->with([
    ['@use(\'App\Models\User\')'],
    ['@php use App\Models\User; @endphp'],
    ['<?php use App\Models\User; ?>'],
    ["<?php\nuse App\Models\User;\n?>"],
    ["<?php\n\tuse App\Models\User;\n?>"],
]);