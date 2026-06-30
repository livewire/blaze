<?php

use Livewire\Blaze\Support\AttributeParser;

test('getStaticValue returns constatnts for dynamic constant values', function () {
    $attribute = app(AttributeParser::class)->parse(':foo="true"')['foo'];

    expect($attribute->isStaticValue())->toBeTrue();
    expect($attribute->getStaticValue())->toBeTrue();
});

test('getStaticValue returns strings for static constant values', function () {
    $attribute = app(AttributeParser::class)->parse('foo="true"')['foo'];

    expect($attribute->isStaticValue())->toBeTrue();
    expect($attribute->getStaticValue())->toBe('true');
});

test('getStaticValue returns true for valueless attributes', function () {
    $attribute = app(AttributeParser::class)->parse('foo')['foo'];

    expect($attribute->isStaticValue())->toBeTrue();
    expect($attribute->getStaticValue())->toBeTrue();
});

test('getStaticValue throws for dynamic attributes', function () {
    $attribute = app(AttributeParser::class)->parse(':foo="$bar"')['foo'];

    $attribute->getStaticValue();
})->throws(LogicException::class);
