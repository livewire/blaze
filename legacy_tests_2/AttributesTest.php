<?php

use Livewire\Blaze\Support\AttributeParser;

beforeEach(function () {
    if (! \Illuminate\View\ComponentAttributeBag::hasMacro('pluck')) {
        \Illuminate\View\ComponentAttributeBag::macro('pluck', function ($key, $default = null) {
            $result = $this->get($key);
            unset($this->attributes[$key]);
            return $result ?? $default;
        });
    }
});

test('boolean attribute rendering', function ($value, $shouldContain, $shouldNotContain) {
    $result = blade(
        view: '<x-button :disabled="$v">Save</x-button>',
        components: ['button' => '@blaze(fold: true, safe: ["disabled"])
@props(["type" => "button"])
<button {{ $attributes->merge(["type" => $type]) }}>{{ $slot }}</button>'],
        data: ['v' => $value],
    );

    if ($shouldContain) expect($result)->toContain($shouldContain);
    if ($shouldNotContain) expect($result)->not->toContain($shouldNotContain);
})->with([
    'false omits'       => [false, 'type="button"', 'disabled'],
    'true renders name' => [true, 'disabled="disabled"', null],
    'null omits'        => [null, 'type="button"', 'disabled'],
    'string renders'    => ['until-loaded', 'disabled="until-loaded"', null],
]);

test('x-data and wire attributes render empty value for boolean true', function ($attrs, $expected) {
    $result = blade(
        view: '<x-test :' . key($attrs) . '="true">Content</x-test>',
        components: ['test' => '@blaze(fold: true, safe: ["' . key($attrs) . '"])
<div {{ $attributes }}>{{ $slot }}</div>'],
    );

    expect($result)->toContain($expected);
})->with([
    'x-data'       => [['x-data' => true], 'x-data=""'],
    'wire:loading'  => [['wire:loading' => true], 'wire:loading=""'],
]);

test('static boolean literals fold without safe list', function ($input, $shouldContain, $shouldNotContain) {
    $result = blade(
        view: $input,
        components: ['button' => '@blaze(fold: true)
@props(["disabled" => false])
<button {{ $attributes->merge(["type" => "button"]) }}>Click</button>'],
    );

    if ($shouldContain) expect($result)->toContain($shouldContain);
    if ($shouldNotContain) expect($result)->not->toContain($shouldNotContain);
})->with([
    ':disabled="false"' => ['<x-button :disabled="false">Save</x-button>', 'type="button"', 'disabled'],
    ':disabled="null"'  => ['<x-button :disabled="null">Save</x-button>', 'type="button"', 'disabled'],
]);

test(':: escaped attributes pass through as literal :attr', function () {
    $result = blade(
        view: '<x-button ::class="{ danger: isDeleting }">Submit</x-button>',
        components: ['button' => '@blaze(fold: true)
@props(["type" => "button"])
<button {{ $attributes->merge(["type" => $type]) }}>{{ $slot }}</button>'],
    );

    expect($result)->toContain(':class="{ danger: isDeleting }"');
});

test('@class directive transforms to dynamic class attribute', function () {
    $result = (new AttributeParser)->parseAttributeStringToArray('@class([\'active\' => $isActive])');

    expect($result['class']['isDynamic'])->toBeTrue();
    expect($result['class']['value'])->toContain('toCssClasses');
});

test('@class renders with conditional classes', function () {
    $result = blade(
        view: '<x-badge @class([\'font-bold\', \'text-red\' => $isError, \'text-green\' => $isSuccess])>Status</x-badge>',
        components: ['badge' => '@blaze(fold: true, safe: ["class"])
<span {{ $attributes }}>{{ $slot }}</span>'],
        data: ['isError' => true, 'isSuccess' => false],
    );

    expect($result)->toContain('font-bold');
    expect($result)->toContain('text-red');
    expect($result)->not->toContain('text-green');
});

test('@style renders with conditional styles', function () {
    $result = blade(
        view: '<x-box @style([\'color: red\' => $isRed])>Content</x-box>',
        components: ['box' => '@blaze(fold: true, safe: ["style"])
<div {{ $attributes }}>{{ $slot }}</div>'],
        data: ['isRed' => true],
    );

    expect($result)->toContain('style="color: red;"');
});

test('plucked colon attributes do not leak into $attributes', function () {
    $result = blade(
        view: '<x-button icon:trailing="arrow" class="btn" />',
        components: ['button' => '@blaze
@php $iconTrailing ??= $attributes->pluck("icon:trailing"); @endphp
@props(["iconTrailing" => null])
<button {{ $attributes }} data-icon="{{ $iconTrailing }}">Click</button>'],
    );

    expect($result)->toContain('data-icon="arrow"');
    expect($result)->toContain('class="btn"');
    expect($result)->not->toContain('icon:trailing');
});

test('framework colon attributes are preserved while custom ones are plucked', function () {
    $result = blade(
        view: '<x-input wire:model="name" x-on:click="handler()" mask:dynamic="999" />',
        components: ['input' => '@blaze
@php $maskDynamic ??= $attributes->pluck("mask:dynamic"); @endphp
@props(["maskDynamic" => null])
<input {{ $attributes }} />'],
    );

    expect($result)->toContain('wire:model="name"');
    expect($result)->toContain('x-on:click="handler()"');
    expect($result)->not->toContain('mask:dynamic');
});

test('mixed content attribute with blade echoes renders interpolated values', function () {
    $result = blade(
        view: '<x-option wire:key="opt-{{ $productId }}-{{ $optionId }}">Item</x-option>',
        components: ['option' => '@blaze(fold: true, safe: ["*"])
<ui-option {{ $attributes }}>{{ $slot }}</ui-option>'],
        data: ['productId' => 42, 'optionId' => 7],
    );

    expect($result)->toContain('wire:key="opt-42-7"');
});
