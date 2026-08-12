<?php

use Livewire\Blaze\Parser\Attribute;
use Livewire\Blaze\Parser\Nodes\ComponentNode;
use Livewire\Blaze\Parser\Nodes\TextNode;

test('renders components with attributes and children', function () {
    $component = new ComponentNode(
        name: 'card',
        prefix: 'x-',
        children: [new TextNode('Content')],
        attributes: [
            new Attribute('class', 'p-2', 'class', false),
            new Attribute('disabled', true, 'disabled', false, valueless: true),
            new Attribute('title', '$title', 'title', true, prefix: ':'),
        ],
    );

    expect($component->render())->toBe('<x-card class="p-2" disabled :title="$title">Content</x-card>');
});

test('renders self-closing components', function () {
    $component = new ComponentNode(
        name: 'button',
        prefix: 'x:',
        selfClosing: true,
    );

    expect($component->render())->toBe('<x:button />');
});

test('renders namespaced Flux components', function () {
    $component = new ComponentNode(
        name: 'flux::button',
        prefix: 'flux:',
    );

    expect($component->render())->toBe('<flux:button></flux:button>');
});
