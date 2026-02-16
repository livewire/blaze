<?php

test('uses default when prop not passed', function () {
    $result = blade(
        view: '<x-button />',
        components: ['button' => '@blaze
@props(["type" => "button"])
<button type="{{ $type }}">Click</button>'],
    );

    expect($result)->toContain('type="button"');
});

test('uses passed value over default', function ($view, $expected, $data) {
    $result = blade(
        view: $view,
        components: ['button' => '@blaze
@props(["type" => "button"])
<button type="{{ $type }}">Click</button>'],
        data: $data,
    );

    expect($result)->toContain("type=\"{$expected}\"");
})->with([
    'static'  => ['<x-button type="submit" />', 'submit', []],
    'dynamic' => ['<x-button :type="$buttonType" />', 'reset', ['buttonType' => 'reset']],
]);

test('null uses default (matches Laravel behavior)', function () {
    $result = blade(
        view: '<x-button :type="$nullType" />',
        components: ['button' => '@blaze
@props(["type" => "button"])
<button type="{{ $type }}">Click</button>'],
        data: ['nullType' => null],
    );

    expect($result)->toContain('type="button"');
});

test('falsy values override defaults', function ($default, $passed, $expectContains) {
    $result = blade(
        view: '<x-test :value="$v" />',
        components: ['test' => '@blaze
@props(["value" => ' . var_export($default, true) . '])
<span>{!! json_encode($value) !!}</span>'],
        data: ['v' => $passed],
    );

    expect($result)->toContain("<span>{$expectContains}</span>");
})->with([
    'false overrides true'     => [true, false, 'false'],
    'zero overrides non-zero'  => [10, 0, '0'],
    'empty string overrides'   => ['button', '', '""'],
]);

test('required props set variable when passed', function () {
    $result = blade(
        view: '<x-label label="Hello" />',
        components: ['label' => '@blaze
@props(["label"])
<span>{{ $label ?? "undefined" }}</span>'],
    );

    expect($result)->toContain('<span>Hello</span>');
});

test('required props leave variable undefined when not passed', function () {
    $result = blade(
        view: '<x-label />',
        components: ['label' => '@blaze
@props(["label"])
<span>{{ $label ?? "undefined" }}</span>'],
    );

    expect($result)->toContain('<span>undefined</span>');
});

test('converts kebab-case attribute to camelCase prop', function () {
    $result = blade(
        view: '<x-box background-color="red" />',
        components: ['box' => '@blaze
@props(["backgroundColor" => "white"])
<div style="background-color: {{ $backgroundColor }}">Content</div>'],
    );

    expect($result)->toContain('background-color: red');
});

test('removes props from attributes bag', function () {
    $result = blade(
        view: '<x-button type="submit" class="btn" />',
        components: ['button' => '@blaze
@props(["type" => "button"])
<button type="{{ $type }}" {{ $attributes }}>Click</button>'],
    );

    expect($result)->toContain('class="btn"');
    expect($result)->toContain('type="submit"');
});

test('named slot overrides prop when both provided', function () {
    $result = blade(
        view: '<x-button name="Josh"><x-slot:name>Caleb</x-slot:name></x-button>',
        components: ['button' => '@blaze
@props(["name" => "Filip"])
<button>Hey {{ $name }}</button>'],
    );

    expect($result)->toContain('Hey Caleb');
    expect($result)->not->toContain('Hey Josh');
});

test('cross-prop references use fallback (Laravel parity)', function () {
    $result = blade(
        view: '<x-test />',
        components: ['test' => '@blaze
@props(["first" => "hello", "second" => $first ?? "fallback"])
<span>{{ $second }}</span>'],
    );

    expect($result)->toContain('<span>fallback</span>');
});

test('$attributes available in @php before @props', function () {
    $result = blade(
        view: '<x-button type="submit" />',
        components: ['button' => '@blaze
@php
    $type = $attributes->get("type", "button");
@endphp
<button type="{{ $type }}">Click</button>'],
    );

    expect($result)->toContain('type="submit"');
});

test('attribute forwarding via :$attributes', function () {
    $result = blade(
        view: '<x-inner :attributes="$attrs" />',
        components: ['inner' => '@blaze
@props(["type" => "submit"])
<button type="{{ $type }}">Click</button>'],
        data: ['attrs' => new \Illuminate\View\ComponentAttributeBag(['type' => 'button', 'class' => 'btn'])],
    );

    expect($result)->toContain('type="button"');
});

test('explicit attributes take precedence over forwarded', function () {
    $result = blade(
        view: '<x-inner :attributes="$attrs" type="reset" />',
        components: ['inner' => '@blaze
@props(["type" => "submit"])
<button type="{{ $type }}">Click</button>'],
        data: ['attrs' => new \Illuminate\View\ComponentAttributeBag(['type' => 'button'])],
    );

    expect($result)->toContain('type="reset"');
});
