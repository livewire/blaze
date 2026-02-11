<?php

use Livewire\Blaze\Support\AttributeParser;

describe('parse attributes', function () {
    it('parses static attributes', function () {
        $input = 'name="Bob" searchable="true"';
        $output = [
            'name' => [
                'name' => 'name',
                'isDynamic' => false,
                'value' => 'Bob',
                'original' => 'name="Bob"',
                'quotes' => '"',
            ],
            'searchable' => [
                'name' => 'searchable',
                'isDynamic' => false,
                'value' => 'true',
                'original' => 'searchable="true"',
                'quotes' => '"',
            ],
        ];

        $attributes = (new AttributeParser())->parseAttributeStringToArray($input);

        expect($attributes)->toBe($output);
    });

    it('parses dynamic attributes', function () {
        $input = ':name="$name" :searchable="true"';
        $output = [
            'name' => [
                'name' => 'name',
                'isDynamic' => true,
                'value' => '$name',
                'original' => ':name="$name"',
                'quotes' => '"',
            ],
            'searchable' => [
                'name' => 'searchable',
                'isDynamic' => true,
                'value' => 'true',
                'original' => ':searchable="true"',
                'quotes' => '"',
            ],
        ];

        $attributes = (new AttributeParser())->parseAttributeStringToArray($input);

        expect($attributes)->toBe($output);
    });

    it('parses dynamic short attributes', function () {
        $input = ':$name';
        $output = [
            'name' => [
                'name' => 'name',
                'isDynamic' => true,
                'value' => '$name',
                'original' => ':$name',
                'quotes' => '"',
            ],
        ];

        $attributes = (new AttributeParser())->parseAttributeStringToArray($input);

        expect($attributes)->toBe($output);
    });

    it('parses boolean attributes', function () {
        $input = 'searchable';
        $output = [
            'searchable' => [
                'name' => 'searchable',
                'isDynamic' => false,
                'value' => true,
                'original' => 'searchable',
                'quotes' => '',
            ],
        ];

        $attributes = (new AttributeParser())->parseAttributeStringToArray($input);

        expect($attributes)->toBe($output);
    });

    it('parses attributes with echoed values', function () {
        $input = 'type="{{ $type }}"';
        $output = [
            'type' => [
                'name' => 'type',
                'isDynamic' => false,
                'value' => '{{ $type }}',
                'original' => 'type="{{ $type }}"',
                'quotes' => '"',
            ],
        ];

        $attributes = (new AttributeParser())->parseAttributeStringToArray($input);

        expect($attributes)->toBe($output);
    });

    it('parses hyphenated static attributes', function () {
        $input = 'data-test="foo" second-variant="secondary"';
        $output = [
            'dataTest' => [
                'name' => 'data-test',
                'isDynamic' => false,
                'value' => 'foo',
                'original' => 'data-test="foo"',
                'quotes' => '"',
            ],
            'secondVariant' => [
                'name' => 'second-variant',
                'isDynamic' => false,
                'value' => 'secondary',
                'original' => 'second-variant="secondary"',
                'quotes' => '"',
            ],
        ];

        $attributes = (new AttributeParser())->parseAttributeStringToArray($input);

        expect($attributes)->toBe($output);
    });

    it('parses hyphenated dynamic attributes', function () {
        $input = ':data-test="$test" :second-variant="true"';
        $output = [
            'dataTest' => [
                'name' => 'data-test',
                'isDynamic' => true,
                'value' => '$test',
                'original' => ':data-test="$test"',
                'quotes' => '"',
            ],
            'secondVariant' => [
                'name' => 'second-variant',
                'isDynamic' => true,
                'value' => 'true',
                'original' => ':second-variant="true"',
                'quotes' => '"',
            ],
        ];

        $attributes = (new AttributeParser())->parseAttributeStringToArray($input);

        expect($attributes)->toBe($output);
    });

    it('parses hyphenated short dynamic attributes', function () {
        $input = ':$data-test';
        $output = [
            'dataTest' => [
                'name' => 'data-test',
                'isDynamic' => true,
                'value' => '$data-test',
                'original' => ':$data-test',
                'quotes' => '"',
            ],
        ];

        $attributes = (new AttributeParser())->parseAttributeStringToArray($input);

        expect($attributes)->toBe($output);
    });

    it('parses static attributes which contain colons', function () {
        $input = 'icon:trailing="chevrons-up-down" wire:sort:item="{{ $id }}"';
        $output = [
            'icon:trailing' => [
                'name' => 'icon:trailing',
                'isDynamic' => false,
                'value' => 'chevrons-up-down',
                'original' => 'icon:trailing="chevrons-up-down"',
                'quotes' => '"',
            ],
            'wire:sort:item' => [
                'name' => 'wire:sort:item',
                'isDynamic' => false,
                'value' => '{{ $id }}',
                'original' => 'wire:sort:item="{{ $id }}"',
                'quotes' => '"',
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

    it('parses escaped :: attributes as static with name preserved', function () {
        $input = '::class="{ danger: isDeleting }"';
        $output = [
            'class' => [
                'name' => 'class',
                'isDynamic' => false,
                'value' => '{ danger: isDeleting }',
                'original' => '::class="{ danger: isDeleting }"',
                'quotes' => '"',
            ],
        ];

        $attributes = (new AttributeParser())->parseAttributeStringToArray($input);

        expect($attributes)->toBe($output);
    });

    it('parses escaped :: attributes alongside bound : attributes', function () {
        $input = ':name="$name" ::class="{ danger: isDeleting }"';
        $output = [
            'name' => [
                'name' => 'name',
                'isDynamic' => true,
                'value' => '$name',
                'original' => ':name="$name"',
                'quotes' => '"',
            ],
            'class' => [
                'name' => 'class',
                'isDynamic' => false,
                'value' => '{ danger: isDeleting }',
                'original' => '::class="{ danger: isDeleting }"',
                'quotes' => '"',
            ],
        ];

        $attributes = (new AttributeParser())->parseAttributeStringToArray($input);

        expect($attributes)->toBe($output);
    });

    it('parses escaped :: attributes with single quotes', function () {
        $input = "::class='{ danger: isDeleting }'";
        $output = [
            'class' => [
                'name' => 'class',
                'isDynamic' => false,
                'value' => '{ danger: isDeleting }',
                'original' => "::class='{ danger: isDeleting }'",
                'quotes' => "'",
            ],
        ];

        $attributes = (new AttributeParser())->parseAttributeStringToArray($input);

        expect($attributes)->toBe($output);
    });

    it('does not parse words within quoted class attribute values as boolean attributes', function () {
        $input = 'class="rounded-lg w-full h-10 opacity-75"';
        $output = [
            'class' => [
                'name' => 'class',
                'isDynamic' => false,
                'value' => 'rounded-lg w-full h-10 opacity-75',
                'original' => 'class="rounded-lg w-full h-10 opacity-75"',
                'quotes' => '"',
            ],
        ];

        $attributes = (new AttributeParser())->parseAttributeStringToArray($input);

        expect($attributes)->toBe($output);
    });
});
