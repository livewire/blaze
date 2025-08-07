<?php

namespace Livewire\Blaze\Nodes;

class SlotNode extends Node
{
    public function __construct(
        public string $name,
        public string $attributes = '',
        public string $slotStyle = 'standard',
        public array $children = [],
    ) {}

    public function getType(): string
    {
        return 'slot';
    }

    public function toArray(): array
    {
        return [
            'type' => $this->getType(),
            'name' => $this->name,
            'attributes' => $this->attributes,
            'slot_style' => $this->slotStyle,
            'children' => array_map(fn($child) => $child instanceof Node ? $child->toArray() : $child, $this->children),
        ];
    }
}