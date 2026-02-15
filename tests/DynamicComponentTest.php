<?php

/*
|--------------------------------------------------------------------------
| Dynamic Component Handling
|--------------------------------------------------------------------------
| <x-dynamic-component> must NOT be compiled by Blaze. It should pass
| through to Laravel's Blade compiler unchanged.
*/

test('dynamic component with static name renders target', function () {
    $result = blade(
        view: '<x-wrapper />',
        components: ['wrapper' => '@blaze
<div class="wrapper">
    <x-dynamic-component component="simple-button" type="submit" />
</div>'],
    );

    expect($result)
        ->toContain('<div class="wrapper">')
        ->toContain('<button')
        ->toContain('type="submit"');
});

test('dynamic component with variable name resolves at runtime', function () {
    $result = blade(
        view: '<x-wrapper component-name="simple-button" />',
        components: ['wrapper' => '@blaze
@props(["componentName"])
<div class="wrapper">
    <x-dynamic-component :component="$componentName" type="button" />
</div>'],
    );

    expect($result)
        ->toContain('<div class="wrapper">')
        ->toContain('<button')
        ->toContain('type="button"');
});

test('dynamic component targets another blaze component', function () {
    $result = blade(
        view: '<x-wrapper />',
        components: ['wrapper' => '@blaze
<div class="outer">
    <x-dynamic-component component="props-simple" foo="Hello" />
</div>'],
    );

    expect($result)
        ->toContain('<div class="outer">')
        ->toContain('<span>Hello</span>');
});

test('dynamic component forwards slot content', function () {
    $result = blade(
        view: '<x-wrapper />',
        components: ['wrapper' => '@blaze
<div class="wrapper">
    <x-dynamic-component component="card-header">
        Slot Content Here
    </x-dynamic-component>
</div>'],
    );

    expect($result)
        ->toContain('<div class="card-body">')
        ->toContain('Slot Content Here');
});

test('dynamic component forwards named slots', function () {
    $result = blade(
        view: '<x-wrapper />',
        components: ['wrapper' => '@blaze
<div class="wrapper">
    <x-dynamic-component component="card-header">
        <x-slot:header>Card Header</x-slot:header>
        Card Body
    </x-dynamic-component>
</div>'],
    );

    expect($result)
        ->toContain('<div class="card-header">Card Header</div>')
        ->toContain('Card Body');
});

test('dynamic component forwards attributes to target', function () {
    $result = blade(
        view: '<x-wrapper />',
        components: ['wrapper' => '@blaze
<x-dynamic-component component="simple-button" class="btn-primary" data-test="value" />'],
    );

    expect($result)
        ->toContain('class="btn-primary"')
        ->toContain('data-test="value"');
});

test('dynamic component is not compiled as blaze function call', function () {
    $result = app('blaze')->compile('@blaze
<x-dynamic-component :component="$name" />');

    expect($result)
        ->not->toContain('$__blaze->ensureCompiled')
        ->not->toContain('$__blaze->resolve');
});
