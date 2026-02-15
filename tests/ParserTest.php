<?php

use Livewire\Blaze\Parser\Parser;
use Livewire\Blaze\Tokenizer\Tokenizer;
use Livewire\Blaze\Nodes\ComponentNode;
use Livewire\Blaze\Nodes\SlotNode;
use Livewire\Blaze\Nodes\TextNode;

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

test('parses components with slots', function () {
    $input = '<x-card class="p-4">Body<x-slot name="footer" class="p-2">Footer</x-slot></x-card>';

    expect(app(Parser::class)->parse($input))->toEqual([
        new ComponentNode(
            name: 'card',
            prefix: 'x-',
            attributeString: 'class="p-4"',
            children: [
                new TextNode('Body'),
                new SlotNode(
                    name: 'footer',
                    attributeString: 'class="p-2"',
                    children: [
                        new TextNode('Footer'),
                    ],
                ),
            ],
        ),
    ]);
});

test('parses slots with short syntax', function () {
    $input = '<x-slot:footer></x-slot:footer>';

    expect(app(Parser::class)->parse($input))->toEqual([
        new SlotNode(
            name: 'footer',
            prefix: 'x-slot',
            slotStyle: 'short',
            closeHasName: true,
        )
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