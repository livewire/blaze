<?php

use Livewire\Blaze\Support\Utils;
use Livewire\Blaze\Compiler\Wrapper;
use Illuminate\Support\Facades\Blade;
use Livewire\Blaze\BladeService;
use Livewire\Blaze\Parser\Parser;

test('wraps component templates into function definitions', function () {
    $path = fixture_path('views/components/input.blade.php');
    $source = file_get_contents($path);
    $hash = Utils::hash($path);

    $ast = app(Parser::class)->parse($source)->nodes;
    $wrapped = join('', array_map(fn ($node) => $node->render(), app(Wrapper::class)->wrap($ast, $path)));

    expect($wrapped)->toEqualCollapsingWhitespace(join('', [
        '<?php if (!function_exists(\'_'. $hash .'\')): function _'. $hash .'($__blaze, $__data = [], $__slots = [], $__bound = [], $__keys = [], $__this = null) { ',
        '$__env = $__blaze->env; ',
        'if (($__data[\'attributes\'] ?? null) instanceof \Illuminate\View\ComponentAttributeBag) { $__data = $__data + $__data[\'attributes\']->all(); unset($__data[\'attributes\']); } ',
        'extract($__slots, EXTR_SKIP); unset($__slots); ',
        'extract($__data, EXTR_SKIP); ',
        '$attributes = \Livewire\Blaze\Runtime\BlazeAttributeBag::make($__data, $__bound, $__keys); ',
        'unset($__data, $__bound, $__keys); ',
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
    $path = fixture_path('views/components/input-aware.blade.php');
    $source = file_get_contents($path);
    $hash = Utils::hash($path);

    $ast = app(Parser::class)->parse($source)->nodes;
    $wrapped = join('', array_map(fn ($node) => $node->render(), app(Wrapper::class)->wrap($ast, $path)));

    expect($wrapped)->toEqualCollapsingWhitespace(join('', [
        '<?php if (!function_exists(\'_'. $hash .'\')): function _'. $hash .'($__blaze, $__data = [], $__slots = [], $__bound = [], $__keys = [], $__this = null) { ',
        '$__env = $__blaze->env; ',
        'if (($__data[\'attributes\'] ?? null) instanceof \Illuminate\View\ComponentAttributeBag) { $__data = $__data + $__data[\'attributes\']->all(); unset($__data[\'attributes\']); } ',
        'extract($__slots, EXTR_SKIP); unset($__slots); ',
        'extract($__data, EXTR_SKIP); ',
        '$attributes = \Livewire\Blaze\Runtime\BlazeAttributeBag::make($__data, $__bound, $__keys); ',
        'unset($__data, $__bound, $__keys); ',
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
    $ast = app(Parser::class)->parse('<div></div>')->nodes;
    $wrapped = join('', array_map(fn ($node) => $node->render(), app(Wrapper::class)->wrap($ast, '')));

    expect($wrapped)->toContain('extract($__data, EXTR_SKIP);');
});

test('wraps in self invoking closure', function ($source) {
    $ast = app(Parser::class)->parse($source)->nodes;
    $wrapped = join('', array_map(fn ($node) => $node->render(), app(Wrapper::class)->wrap($ast, '')));

    expect($wrapped)->toContain(
        '$__blazeFn = function () use ($__blaze, $__data, $__slots, $__bound, $__keys) {',
        'if ($__this !== null) { $__blazeFn->call($__this); } else { $__blazeFn(); }',
    );
})->with([
    '{{ $this->orders }}',
    '@entangle(\'name\')',
    '@script',
    '@assets',
]);

test('injects variables', function ($source, $expected) {
    $ast = app(Parser::class)->parse($source)->nodes;
    $wrapped = join('', array_map(fn ($node) => $node->render(), app(Wrapper::class)->wrap($ast, '')));

    expect($wrapped)->toContain($expected);
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

    $ast = app(Parser::class)->parse('{{ $a}}')->nodes;
    $wrapped = join('', array_map(fn ($node) => $node->render(), app(Wrapper::class)->wrap($ast, '')));

    expect($wrapped)->toContain('$__bladeCompiler = app(\'blade.compiler\');');
});

test('hoists use statements to top of output', function ($statement) {
    $ast = app(Parser::class)->parse($statement)->nodes;
    $wrapped = join('', array_map(fn ($node) => $node->render(), app(Wrapper::class)->wrap($ast, '')));

    expect($wrapped)->toStartWith("<?php\nuse \App\Models\User");
})->with([
    ['@use(\'App\Models\User\')'],
    ['@php use \App\Models\User; @endphp'],
    ['<?php use \App\Models\User; ?>'],
]);

test('preserves php directives', function () {
    $input = '@php /* uncompiled */ @endphp';
    $ast = app(Parser::class)->parse($input)->nodes;
    $wrapped = join('', array_map(fn ($node) => $node->render(), app(Wrapper::class)->wrap($ast, '')));

    expect($wrapped)->toContain('@php /* uncompiled */ @endphp');
});

test('preserves verbatim directives', function () {
    $input = '@verbatim /* uncompiled */ @endverbatim';
    $ast = app(Parser::class)->parse($input)->nodes;
    $wrapped = join('', array_map(fn ($node) => $node->render(), app(Wrapper::class)->wrap($ast, '')));

    expect($wrapped)->toContain($input);
});
