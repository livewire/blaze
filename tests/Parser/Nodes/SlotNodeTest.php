<?php

use Livewire\Blaze\Parser\Nodes\SlotNode;
use Livewire\Blaze\Parser\Nodes\TextNode;

test('renders slots with attributes and children', function () {
    $slot = new SlotNode(
        name: 'footer',
        attributeString: 'class="p-2"',
        children: [new TextNode('Footer')],
    );

    expect($slot->render())->toBe('<x-slot name="footer" class="p-2">Footer</x-slot>');
});

test('renders short slots', function () {
    $slot = new SlotNode(
        name: 'footer',
        slotStyle: 'short',
        children: [new TextNode('Footer')],
        closeHasName: true,
    );

    expect($slot->render())->toBe('<x-slot:footer>Footer</x-slot:footer>');
});
