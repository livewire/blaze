<?php

use Livewire\Blaze\Support\AttributeParser;

test('parses bound attributes', function () {
    $attrs = (new AttributeParser)->parseAttributeStringToArray(':foo="bar"');

    expect($attrs)->toHaveKey('foo');
    expect($attrs['foo'])
        ->name->toBe('foo')
        ->value->toBe('bar')
        ->prefix->toBe(':')
        ->dynamic->toBeTrue();
});

test('parses escaped bound attributes', function () {
    $attrs = (new AttributeParser)->parseAttributeStringToArray('::key="value"');

    expect($attrs)->toHaveKey(':key');
    expect($attrs[':key'])
        ->name->toBe(':key')
        ->value->toBe('value')
        ->prefix->toBe('::')
        ->dynamic->toBeFalse();
});

test('parses attributes without value', function () {
    $attrs = (new AttributeParser)->parseAttributeStringToArray('disabled');

    expect($attrs)->toHaveKey('disabled');
    expect($attrs['disabled'])
        ->name->toBe('disabled')
        ->value->toBeTrue()
        ->dynamic->toBeFalse()
        ->quotes->toBe('');
});

test('parses attributes with blade echo', function () {
    $attrs = (new AttributeParser)->parseAttributeStringToArray('title="{{ $x }}"');

    expect($attrs)->toHaveKey('title');
    expect($attrs['title'])
        ->name->toBe('title')
        ->value->toBe('{{ $x }}')
        ->dynamic->toBeTrue();
});

test('parses attributes with raw blade echo', function () {
    $attrs = (new AttributeParser)->parseAttributeStringToArray('title="{!! $x !!}"');

    expect($attrs)->toHaveKey('title');
    expect($attrs['title'])
        ->name->toBe('title')
        ->value->toBe('{!! $x !!}')
        ->dynamic->toBeTrue();
});

test('parses quotes', function () {
    $attrs = (new AttributeParser)->parseAttributeStringToArray('double="hello" single=\'hello\'');

    expect($attrs['double']->quotes)->toBe('"');
    expect($attrs['single']->quotes)->toBe("'");
});

test('parses kebab case attributes', function () {
    $attrs = (new AttributeParser)->parseAttributeStringToArray('foo-bar="first"');

    expect($attrs)->toHaveKey('fooBar');
    expect($attrs['fooBar'])
        ->name->toBe('foo-bar')
        ->propName->toBe('fooBar');
});

test('keeps first attribute when multiple camelize to same key', function () {
    $attrs = (new AttributeParser)->parseAttributeStringToArray('foo-bar="first" foo_bar="second"');

    expect($attrs)->toHaveCount(1)->toHaveKey('fooBar');
    expect($attrs['fooBar']->value)->toBe('first');
});