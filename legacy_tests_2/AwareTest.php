<?php

test('child gets prop from parent via @aware', function () {
    $result = blade(
        view: '<x-menu color="blue"><x-menu-item>Item</x-menu-item></x-menu>',
        components: [
            'menu' => '@blaze
@props(["color" => "gray"])
<ul class="bg-{{ $color }}-100">{{ $slot }}</ul>',
            'menu-item' => '@blaze
@aware(["color" => "gray"])
<li class="text-{{ $color }}-800">{{ $slot }}</li>',
        ],
    );

    expect($result)->toContain('bg-blue-100');
    expect($result)->toContain('text-blue-800');
});

test('uses default when parent does not have the prop', function () {
    $result = blade(
        view: '<x-child>Item</x-child>',
        components: [
            'child' => '@blaze
@aware(["color" => "gray"])
<li class="text-{{ $color }}-800">{{ $slot }}</li>',
        ],
    );

    expect($result)->toContain('text-gray-800');
});

test('grandchild gets prop from grandparent', function () {
    $result = blade(
        view: '<x-outer variant="success"><x-middle spacing="tight"><x-inner>Text</x-inner></x-middle></x-outer>',
        components: [
            'outer' => '@blaze
@props(["variant" => "primary"])
<div class="outer-{{ $variant }}">{{ $slot }}</div>',
            'middle' => '@blaze
@props(["spacing" => "normal"])
<div class="middle-{{ $spacing }}">{{ $slot }}</div>',
            'inner' => '@blaze
@aware(["variant" => "default", "spacing" => "default"])
<span class="inner-{{ $variant }}-{{ $spacing }}">{{ $slot }}</span>',
        ],
    );

    expect($result)->toContain('inner-success-tight');
});

test('nearest ancestor wins for duplicate props', function () {
    $result = blade(
        view: '<x-outer color="red"><x-middle color="blue"><x-inner>Text</x-inner></x-middle></x-outer>',
        components: [
            'outer' => '@blaze
@props(["color" => "outer"])
<div>{{ $slot }}</div>',
            'middle' => '@blaze
@props(["color" => "middle"])
<div>{{ $slot }}</div>',
            'inner' => '@blaze
@aware(["color" => "inner"])
<span class="level-inner-{{ $color }}">{{ $slot }}</span>',
        ],
    );

    expect($result)->toContain('level-inner-blue');
});

test('works with both @props and @aware', function () {
    $result = blade(
        view: '<x-menu color="blue"><x-child label="Click Me" /></x-menu>',
        components: [
            'menu' => '@blaze
@props(["color" => "gray"])
<ul class="bg-{{ $color }}-100">{{ $slot }}</ul>',
            'child' => '@blaze
@props(["label" => "Button"])
@aware(["color" => "gray"])
<button class="text-{{ $color }}">{{ $label }}</button>',
        ],
    );

    expect($result)->toContain('Click Me');
    expect($result)->toContain('text-blue');
});

test('aware keys do not leak into attributes', function () {
    $result = blade(
        view: '<x-parent mode="numeric"><x-child class="input" /></x-parent>',
        components: [
            'parent' => '@blaze
@props(["mode" => "numeric"])
<div>{{ $slot }}</div>',
            'child' => '@blaze
@aware(["mode" => "numeric"])
<input type="text" {{ $attributes }} />',
        ],
    );

    expect($result)->toContain('class="input"');
    expect($result)->not->toMatch('/\bmode="numeric"/');
});

test('sibling components get correct stack values', function () {
    $result = blade(
        view: '<x-menu color="red"><x-item>Red</x-item></x-menu><x-menu color="blue"><x-item>Blue</x-item></x-menu>',
        components: [
            'menu' => '@blaze
@props(["color" => "gray"])
<ul class="bg-{{ $color }}">{{ $slot }}</ul>',
            'item' => '@blaze
@aware(["color" => "gray"])
<li class="text-{{ $color }}">{{ $slot }}</li>',
        ],
    );

    expect($result)->toContain('text-red');
    expect($result)->toContain('text-blue');
});
