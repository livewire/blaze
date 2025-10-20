<?php

use Livewire\Blaze\Support\AttributeParser;

describe('parse attributes', function () {
    it('parses and replaces dynamic attributes with name and value syntax', function() {
        $input = ':name="$foo"';
        $output = 'name="ATTR_PLACEHOLDER_0"';

        $attributePlaceholders = [];
        $attributeNameToPlaceholder = [];

        $result = (new AttributeParser)->parseAndReplaceDynamics($input, $attributePlaceholders, $attributeNameToPlaceholder);

        expect($result)->toBe($output);

        expect($attributePlaceholders)->toBe([
            'ATTR_PLACEHOLDER_0' => '{{ $foo }}',
        ]);

        expect($attributeNameToPlaceholder)->toBe([
            'name' => 'ATTR_PLACEHOLDER_0',
        ]);
    });

    it('parses and replaces dynamic attributes with short syntax', function() {
        $input = ':$name';
        $output = 'name="ATTR_PLACEHOLDER_0"';

        $attributePlaceholders = [];
        $attributeNameToPlaceholder = [];

        $result = (new AttributeParser)->parseAndReplaceDynamics($input, $attributePlaceholders, $attributeNameToPlaceholder);

        expect($result)->toBe($output);

        expect($attributePlaceholders)->toBe([
            'ATTR_PLACEHOLDER_0' => '{{ $name }}',
        ]);

        expect($attributeNameToPlaceholder)->toBe([
            'name' => 'ATTR_PLACEHOLDER_0',
        ]);
    });

    it('parses and replaces dynamic attributes echoed within a value', function() {
        $input = 'name="foo {{ $type }}"';
        $output = 'name="foo ATTR_PLACEHOLDER_0"';

        $attributePlaceholders = [];
        $attributeNameToPlaceholder = [];

        $result = (new AttributeParser)->parseAndReplaceDynamics($input, $attributePlaceholders, $attributeNameToPlaceholder);

        expect($result)->toBe($output);

        expect($attributePlaceholders)->toBe([
            'ATTR_PLACEHOLDER_0' => '{{ $type }}',
        ]);

        expect($attributeNameToPlaceholder)->toBe([]);
    });

    it('does not parse static attributes with colon in the name when used alone', function() {
        $input = 'icon:trailing="chevrons-up-down"';
        $output = 'icon:trailing="chevrons-up-down"';

        $attributePlaceholders = [];
        $attributeNameToPlaceholder = [];

        $result = (new AttributeParser)->parseAndReplaceDynamics($input, $attributePlaceholders, $attributeNameToPlaceholder);

        expect($result)->toBe($output);

        expect($attributePlaceholders)->toBe([]);

        expect($attributeNameToPlaceholder)->toBe([]);
    });

    it('does not parse static attributes with colon in the name when used with dynamic attributes', function() {
        $input = ':name="$foo" icon:trailing="chevrons-up-down"';
        $output = 'name="ATTR_PLACEHOLDER_0" icon:trailing="chevrons-up-down"';

        $attributePlaceholders = [];
        $attributeNameToPlaceholder = [];

        $result = (new AttributeParser)->parseAndReplaceDynamics($input, $attributePlaceholders, $attributeNameToPlaceholder);

        expect($result)->toBe($output);

        expect($attributePlaceholders)->toBe([
            'ATTR_PLACEHOLDER_0' => '{{ $foo }}',
        ]);

        expect($attributeNameToPlaceholder)->toBe([
            'name' => 'ATTR_PLACEHOLDER_0',
        ]);
    });

    it('parses static attributes', function () {
        $input = 'name="Bob" searchable="true"';
        $output = [
            'name' => [
                'isDynamic' => false,
                'value' => 'Bob',
                'original' => 'name="Bob"',
            ],
            'searchable' => [
                'isDynamic' => false,
                'value' => 'true',
                'original' => 'searchable="true"',
            ],
        ];

        $attributes = (new AttributeParser())->parseAttributeStringToArray($input);

        expect($attributes)->toBe($output);
    });

    it('parses dynamic attributes', function () {
        $input = ':name="$name" :searchable="true"';
        $output = [
            'name' => [
                'isDynamic' => true,
                'value' => '$name',
                'original' => ':name="$name"',
            ],
            'searchable' => [
                'isDynamic' => true,
                'value' => 'true',
                'original' => ':searchable="true"',
            ],
        ];

        $attributes = (new AttributeParser())->parseAttributeStringToArray($input);

        expect($attributes)->toBe($output);
    });

    it('parses dynamic short attributes', function () {
        $input = ':$name';
        $output = [
            'name' => [
                'isDynamic' => true,
                'value' => '$name',
                'original' => ':$name',
            ],
        ];

        $attributes = (new AttributeParser())->parseAttributeStringToArray($input);

        expect($attributes)->toBe($output);
    });

    it('parses boolean attributes', function () {
        $input = 'searchable';
        $output = [
            'searchable' => [
                'isDynamic' => false,
                'value' => true,
                'original' => 'searchable',
            ],
        ];

        $attributes = (new AttributeParser())->parseAttributeStringToArray($input);

        expect($attributes)->toBe($output);
    });

    it('parses attributes with echoed values', function () {
        $input = 'type="{{ $type }}"';
        $output = [
            'type' => [
                'isDynamic' => false,
                'value' => '{{ $type }}',
                'original' => 'type="{{ $type }}"',
            ],
        ];

        $attributes = (new AttributeParser())->parseAttributeStringToArray($input);

        expect($attributes)->toBe($output);
    });

    it('parses hyphenated static attributes', function () {
        $input = 'data-test="foo" second-variant="secondary"';
        $output = [
            'dataTest' => [
                'isDynamic' => false,
                'value' => 'foo',
                'original' => 'data-test="foo"',
            ],
            'secondVariant' => [
                'isDynamic' => false,
                'value' => 'secondary',
                'original' => 'second-variant="secondary"',
            ],
        ];

        $attributes = (new AttributeParser())->parseAttributeStringToArray($input);

        expect($attributes)->toBe($output);
    });

    it('parses hyphenated dynamic attributes', function () {
        $input = ':data-test="$test" :second-variant="true"';
        $output = [
            'dataTest' => [
                'isDynamic' => true,
                'value' => '$test',
                'original' => ':data-test="$test"',
            ],
            'secondVariant' => [
                'isDynamic' => true,
                'value' => 'true',
                'original' => ':second-variant="true"',
            ],
        ];

        $attributes = (new AttributeParser())->parseAttributeStringToArray($input);

        expect($attributes)->toBe($output);
    });

    it('parses hyphenated short dynamic attributes', function () {
        $input = ':$data-test';
        $output = [
            'dataTest' => [
                'isDynamic' => true,
                'value' => '$data-test',
                'original' => ':$data-test',
            ],
        ];

        $attributes = (new AttributeParser())->parseAttributeStringToArray($input);

        expect($attributes)->toBe($output);
    });

    it('parses static attributes which contain colons', function () {
        $input = 'icon:trailing="chevrons-up-down" wire:sort:item="{{ $id }}"';
        $output = [
            'icon:trailing' => [
                'isDynamic' => false,
                'value' => 'chevrons-up-down',
                'original' => 'icon:trailing="chevrons-up-down"',
            ],
            'wire:sort:item' => [
                'isDynamic' => false,
                'value' => '{{ $id }}',
                'original' => 'wire:sort:item="{{ $id }}"',
            ],
        ];

        $attributes = (new AttributeParser())->parseAttributeStringToArray($input);

        expect($attributes)->toBe($output);
    });

    it('parses an attributes array and converts it to an attributes string', function () {
        $input = [
            'foo' => [
                'isDynamic' => false,
                'value' => 'bar',
                'original' => 'foo="bar"',
            ],
            'name' => [
                'isDynamic' => true,
                'value' => '$name',
                'original' => ':name="$name"',
            ],
            'baz' => [
                'isDynamic' => true,
                'value' => '$baz',
                'original' => ':$baz',
            ],
            'searchable' => [
                'isDynamic' => false,
                'value' => true,
                'original' => 'searchable',
            ],
        ];

        $output = 'foo="bar" :name="$name" :$baz searchable';

        $attributes = (new AttributeParser())->parseAttributesArrayToPropString($input);

        expect($attributes)->toBe($output);
    });

    it('parses an array string and converts it to an array', function () {
        $input = '[\'variant\', \'secondVariant\' => null]';
        $output = [
            'variant',
            'secondVariant' => null,
        ];

        $attributes = (new AttributeParser())->parseArrayStringIntoArray($input);

        expect($attributes)->toBe($output);
    });

    it('parses an array string with multiline formatting and converts it to an array', function () {
        $input = '[
            \'variant\',
            \'secondVariant\' => null
        ]';
        $output = [
            'variant',
            'secondVariant' => null,
        ];

        $attributes = (new AttributeParser())->parseArrayStringIntoArray($input);

        expect($attributes)->toBe($output);
    });

    it('parses an array string with double quotes and converts it to an array', function () {
        $input = '["variant", "secondVariant" => null]';
        $output = [
            'variant',
            'secondVariant' => null,
        ];

        $attributes = (new AttributeParser())->parseArrayStringIntoArray($input);

        expect($attributes)->toBe($output);
    });
});
