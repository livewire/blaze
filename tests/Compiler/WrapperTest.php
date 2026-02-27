<?php

use Livewire\Blaze\Support\Utils;
use Livewire\Blaze\Compiler\Wrapper;
use Illuminate\Support\Facades\Blade;
use Livewire\Blaze\BladeService;

test('wraps component templates into function definitions', function () {
    $path = fixture_path('components/input.blade.php');
    $source = file_get_contents($path);
    $hash = Utils::hash($path);

    $wrapped = app(Wrapper::class)->wrap($source, $path, $source);

    expect($wrapped)->toEqualCollapsingWhitespace(join('', [
        '<?php if (!function_exists(\'_'. $hash .'\')): function _'. $hash .'($__blaze, $__data = [], $__slots = [], $__bound = [], $__this = null) { ',
        '$__env = $__blaze->env; ',
        'extract($__env->getShared(), EXTR_SKIP); ',
        'if (($__data[\'attributes\'] ?? null) instanceof \Illuminate\View\ComponentAttributeBag) { $__data = $__data + $__data[\'attributes\']->all(); unset($__data[\'attributes\']); } ',
        '$attributes = \Livewire\Blaze\Runtime\BlazeAttributeBag::sanitized($__data, $__bound); ',
        'extract($__slots, EXTR_SKIP); unset($__slots); ',
        'extract($__data, EXTR_SKIP); unset($__data, $__bound); ',
        'ob_start(); ?> ',
        '@blaze ',
        '<?php $__defaults = [\'type\' => \'text\', \'disabled\' => false]; ',
        '$type ??= $attributes[\'type\'] ?? $__defaults[\'type\']; unset($attributes[\'type\']); ',
        '$disabled ??= $attributes[\'disabled\'] ?? $__defaults[\'disabled\']; unset($attributes[\'disabled\']); ',
        'unset($__defaults); ?> ',
        '<input {{ $attributes }} type="{{ $type }}" @if ($disabled) disabled @endif >',
        '<?php echo ltrim(ob_get_clean()); } endif; ?>',
    ]));
});

test('compiles aware props', function () {
    $path = fixture_path('components/input-aware.blade.php');
    $source = file_get_contents($path);
    $hash = Utils::hash($path);

    $wrapped = app(Wrapper::class)->wrap($source, $path, $source);

    expect($wrapped)->toEqualCollapsingWhitespace(join('', [
        '<?php if (!function_exists(\'_'. $hash .'\')): function _'. $hash .'($__blaze, $__data = [], $__slots = [], $__bound = [], $__this = null) { ',
        '$__env = $__blaze->env; ',
        'extract($__env->getShared(), EXTR_SKIP); ',
        'if (($__data[\'attributes\'] ?? null) instanceof \Illuminate\View\ComponentAttributeBag) { $__data = $__data + $__data[\'attributes\']->all(); unset($__data[\'attributes\']); } ',
        '$attributes = \Livewire\Blaze\Runtime\BlazeAttributeBag::sanitized($__data, $__bound); ',
        'extract($__slots, EXTR_SKIP); unset($__slots); ',
        'extract($__data, EXTR_SKIP); unset($__data, $__bound); ',
        'ob_start(); ?> ',
        '@blaze ',
        '<?php $__awareDefaults = [\'type\' => \'text\']; ',
        '$type = $__blaze->getConsumableData(\'type\', $__awareDefaults[\'type\']); unset($attributes[\'type\']); ',
        'unset($__awareDefaults); ?> ',
        '<?php $__defaults = [\'type\' => \'text\', \'disabled\' => false]; ',
        '$type ??= $attributes[\'type\'] ?? $__defaults[\'type\']; unset($attributes[\'type\']); ',
        '$disabled ??= $attributes[\'disabled\'] ?? $__defaults[\'disabled\']; unset($attributes[\'disabled\']); ',
        'unset($__defaults); ?> ',
        '<input {{ $attributes }} type="{{ $type }}" @if ($disabled) disabled @endif >',
        '<?php echo ltrim(ob_get_clean()); } endif; ?>',
    ]));
});

test('extracts props when props are not defined', function () {
    expect(app(Wrapper::class)->wrap('<div></div>', ''))->toContain('extract($__data, EXTR_SKIP);');
});

test('wraps in self invoking closure', function ($source) {
    expect(app(Wrapper::class)->wrap($source, ''))->toContain(
        '$__blazeFn = function () use ($__blaze, $__data, $__slots, $__bound) {',
        'if ($__this !== null) { $__blazeFn->call($__this); } else { $__blazeFn(); }',
    );
})->with([
    '{{ $this->orders }}',
    '@entangle(\'name\')',
    '@script',
]);

test('injects variables', function ($source, $expected) {
    expect(app(Wrapper::class)->wrap('', '', $source))->toContain($expected);
    expect(app(Wrapper::class)->wrap($source, '', ''))->toContain($expected);
})->with([
    'errors' => ['{{ $errors->has(\'name\') }}', '$errors = $__blaze->errors;'],
    'errors directive' => ['<input @error(\'name\') invalid @enderror >', '$errors = $__blaze->errors;'],
    'livewire' => ['{{ $__livewire->id }}', '$__livewire = $__env->shared(\'__livewire\');'],
    'entangle' => ['<div x-data="{ name: @entangle(\'name\') }"></div>', '$__livewire = $__env->shared(\'__livewire\');'],
    'this directive' => ['<script> console.log(@this) </script>', '$__livewire = $__env->shared(\'__livewire\');' . "\n" . '$_instance = $__livewire;'],
    'app' => ['{{ $app->name }}', '$app = $__blaze->app;'],
    'slot' => ['{{ $slot }}', '$__slots[\'slot\'] ??= new \Illuminate\View\ComponentSlot(\'\');'],
]);

test('injects echo handler', function () {
    Blade::stringable((new class {})::class, fn () => 'dummy');

    expect(app(Wrapper::class)->wrap('{{ $a }}', ''))->toContain('$__bladeCompiler = app(\'blade.compiler\');');
});

test('hoists use statements to top of output', function ($statement) {
    // Replace raw @php blocks for placeholders. This normally happens in BlazeManager before the template gets to the Wrapper
    $source = BladeService::preStoreUncompiledBlocks($statement);

    expect(app(Wrapper::class)->wrap($source, '', $source))->toStartWith("<?php\nuse \App\Models\User");
})->with([
    ['@use(\'App\Models\User\')'],
    ['@php use \App\Models\User; @endphp'],
    ['<?php use \App\Models\User; ?>'],
]);

test('preserves php directives', function () {
    $input = '@php something @endphp';

    expect(app(Wrapper::class)->wrap($input, ''))->toContain($input);
});