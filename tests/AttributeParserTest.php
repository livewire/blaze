<?php

use Livewire\Blaze\Support\AttributeParser;
use Livewire\Blaze\Compiler\ArrayParser;

test('parses static attributes', function () {
    $result = (new AttributeParser)->parseAttributeStringToArray('name="Bob" searchable="true"');

    expect($result['name']['value'])->toBe('Bob');
    expect($result['name']['isDynamic'])->toBeFalse();
    expect($result['searchable']['value'])->toBe('true');
});

test('parses dynamic attributes', function () {
    $result = (new AttributeParser)->parseAttributeStringToArray(':name="$name" :searchable="true"');

    expect($result['name']['isDynamic'])->toBeTrue();
    expect($result['name']['value'])->toBe('$name');
    expect($result['name']['prefix'])->toBe(':');
    expect($result['searchable']['isDynamic'])->toBeTrue();
    expect($result['searchable']['value'])->toBe('true');
    expect($result['searchable']['prefix'])->toBe(':');
});

test('parses dynamic short syntax', function () {
    $result = (new AttributeParser)->parseAttributeStringToArray(':$name');

    expect($result['name']['isDynamic'])->toBeTrue();
    expect($result['name']['value'])->toBe('$name');
    expect($result['name']['prefix'])->toBe(':$');
});

test('parses boolean attributes', function () {
    $result = (new AttributeParser)->parseAttributeStringToArray('searchable');

    expect($result['searchable']['value'])->toBeTrue();
    expect($result['searchable']['isDynamic'])->toBeFalse();
});

test('parses attributes with echoed values', function () {
    $result = (new AttributeParser)->parseAttributeStringToArray('type="{{ $type }}"');

    expect($result['type']['value'])->toBe('{{ $type }}');
    expect($result['type']['isDynamic'])->toBeFalse();
});

test('parses escaped :: attributes as static', function () {
    $result = (new AttributeParser)->parseAttributeStringToArray('::class="{ danger: isDeleting }"');

    expect($result['class']['isDynamic'])->toBeFalse();
    expect($result['class']['value'])->toBe('{ danger: isDeleting }');
});

test('camelCases hyphenated attribute keys', function ($input, $expectedKey) {
    $result = (new AttributeParser)->parseAttributeStringToArray($input);

    expect($result)->toHaveKey($expectedKey);
})->with([
    'static'        => ['data-test="foo"', 'dataTest'],
    'dynamic'       => [':data-test="$test"', 'dataTest'],
    'short dynamic' => [':$data-test', 'dataTest'],
]);

test('preserves original attribute name in name field', function () {
    $result = (new AttributeParser)->parseAttributeStringToArray('data-test="foo"');

    expect($result['dataTest']['name'])->toBe('data-test');
});

test('parses attributes with colons and dot modifiers', function ($input, $expectedKey, $expectedName) {
    $result = (new AttributeParser)->parseAttributeStringToArray($input);

    expect($result)->toHaveKey($expectedKey);
    expect($result[$expectedKey]['name'])->toBe($expectedName);
})->with([
    'flux-style colon'      => ['icon:trailing="chevrons-up-down"', 'icon:trailing', 'icon:trailing'],
    'wire directive'        => ['wire:model.live="search"', 'wire:model.live', 'wire:model.live'],
    'alpine event'          => ['x-on:scroll.window="handleScroll()"', 'xOn:scroll.window', 'x-on:scroll.window'],
    'boolean with modifier' => ['wire:loading.remove', 'wire:loading.remove', 'wire:loading.remove'],
    'multiple modifiers'    => ['x-on:keydown.shift.enter="submit()"', 'xOn:keydown.shift.enter', 'x-on:keydown.shift.enter'],
]);

test('does not parse words inside quoted values as boolean attributes', function () {
    $result = (new AttributeParser)->parseAttributeStringToArray('class="rounded-lg w-full h-10 opacity-75"');

    expect($result)->toHaveCount(1);
    expect($result['class']['value'])->toBe('rounded-lg w-full h-10 opacity-75');
});

test('parses $attributes echo as dynamic', function ($input) {
    $result = (new AttributeParser)->parseAttributeStringToArray($input);

    expect($result)->toHaveKey('attributes');
    expect($result['attributes']['isDynamic'])->toBeTrue();
})->with([
    'bare'     => ['{{ $attributes }}'],
    'merge'    => ['{{ $attributes->merge([\'class\' => \'btn\']) }}'],
    'class'    => ['{{ $attributes->class(\'mt-4\') }}'],
]);

test('reconstructs attribute string from parsed array', function () {
    $input = [
        'foo' => ['isDynamic' => false, 'value' => 'bar', 'original' => 'foo="bar"'],
        'name' => ['isDynamic' => true, 'value' => '$name', 'original' => ':name="$name"'],
        'baz' => ['isDynamic' => true, 'value' => '$baz', 'original' => ':$baz'],
        'searchable' => ['isDynamic' => false, 'value' => true, 'original' => 'searchable'],
    ];

    $result = (new AttributeParser)->parseAttributesArrayToPropString($input);

    expect($result)->toBe('foo="bar" :name="$name" :$baz searchable');
});

test('ArrayParser parses array expressions', function ($input, $expected) {
    expect(ArrayParser::parse($input))->toBe($expected);
})->with([
    'simple'      => ["['variant', 'secondVariant' => null]", ['variant', 'secondVariant' => null]],
    'multiline'   => ["[\n    'variant',\n    'secondVariant' => null\n]", ['variant', 'secondVariant' => null]],
    'double quotes' => ['["variant", "secondVariant" => null]', ['variant', 'secondVariant' => null]],
]);
