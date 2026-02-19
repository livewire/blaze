<?php

use Livewire\Blaze\Parser\Parser;
use Livewire\Blaze\Parser\Nodes\ComponentNode;
use Livewire\Blaze\Parser\Nodes\SlotNode;
use Livewire\Blaze\Parser\Nodes\TextNode;
use Livewire\Blaze\Parser\Attribute;

test('parses self-closing components', function () {
    $input = '<x-button class="my-4" />';

    expect(app(Parser::class)->parse($input))->toEqual([
        new ComponentNode(
            name: 'button',
            prefix: 'x-',
            selfClosing: true,
            attributeString: 'class="my-4"',
        ),
    ]);
});

test('parses named slots', function () {
    $input = '<x-card><x-slot name="footer" class="p-2">Footer</x-slot></x-card>';

    expect(app(Parser::class)->parse($input))->toEqual([
        new ComponentNode(
            name: 'card',
            prefix: 'x-',
            children: [
                new SlotNode(
                    name: 'footer',
                    attributeString: 'class="p-2"',
                    children: [
                        new TextNode('Footer'),
                    ],
                )
            ]
        ),
    ]);
});

test('parses named slots with short syntax', function () {
    $input = '<x-card><x-slot:footer class="p-2">Footer</x-slot></x-card>';

    expect(app(Parser::class)->parse($input))->toEqual([
        new ComponentNode(
            name: 'card',
            prefix: 'x-',
            children: [
                new SlotNode(
                    name: 'footer',
                    slotStyle: 'short',
                    attributeString: 'class="p-2"',
                    children: [
                        new TextNode('Footer'),
                    ],
                )
            ]
        ),
    ]);
});

test('parses named slots with short syntax and name in close tag', function () {
    $input = '<x-card><x-slot:footer class="p-2">Footer</x-slot:footer></x-card>';

    expect(app(Parser::class)->parse($input))->toEqual([
        new ComponentNode(
            name: 'card',
            prefix: 'x-',
            children: [
                new SlotNode(
                    name: 'footer',
                    slotStyle: 'short',
                    attributeString: 'class="p-2"',
                    closeHasName: true,
                    children: [
                        new TextNode('Footer'),
                    ],
                )
            ]
        ),
    ]);
});

test('parses explicit default slot', function () {
    $input = '<x-card><x-slot class="p-2">Body</x-slot></x-card>';

    expect(app(Parser::class)->parse($input))->toEqual([
        new ComponentNode(
            name: 'card',
            prefix: 'x-',
            children: [
                new SlotNode(
                    name: 'slot',
                    attributeString: 'class="p-2"',
                    children: [
                        new TextNode('Body'),
                    ],
                )
            ]
        ),
    ]);
});

test('parses component prefixes', function ($input, $prefix, $name) {
    expect(app(Parser::class)->parse($input))->toEqual([
        new ComponentNode($name, $prefix),
    ]);
})->with([
    'standard' => ['<x-button>', 'x-', 'button'],
    'short' => ['<x:button>', 'x:', 'button'],
    'namespaced' => ['<x-ui::button>', 'x-', 'ui::button'],
    'flux' => ['<flux:button>', 'flux:', 'flux::button'],
]);

test('handles attributes with angled brackets', function ($attributes) {
    $input = '<x-button '. $attributes .' />';

    expect(app(Parser::class)->parse($input))->toEqual([
        new ComponentNode(
            name: 'button',
            prefix: 'x-',
            attributeString: $attributes,
            selfClosing: true,
        ),
    ]);
})->with([
    'array' => [':data="[\'foo\' => \'bar\']"'],
    'lambda' => [':callback="fn () => 0"'],
]);

test('handles attributes with quotes inside echos', function () {
    $input = '<x-button x-text="\'{{ __("Print") }}\'" />';

    expect(app(Parser::class)->parse($input))->toEqual([
        new ComponentNode(
            name: 'button',
            prefix: 'x-',
            attributeString: 'x-text="\'{{ __("Print") }}\'"',
            selfClosing: true,
            attributes: [
                'xText' => new Attribute(
                    name: 'x-text',
                    value: '\'{{ __("Print") }}\'',
                    propName: 'xText',
                    dynamic: true,
                    prefix: '',
                    quotes: '"',
                ),
            ],
        ),
    ]);
});