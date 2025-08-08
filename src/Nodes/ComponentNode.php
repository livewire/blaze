<?php

namespace Livewire\Blaze\Nodes;

class ComponentNode extends Node
{
    public function __construct(
        public string $name,
        public string $prefix,
        public string $attributes = '',
        public array $children = [],
        public bool $selfClosing = false,
    ) {}

    public function getType(): string
    {
        return 'component';
    }

    public function toArray(): array
    {
        $array = [
            'type' => $this->getType(),
            'name' => $this->name,
            'prefix' => $this->prefix,
            'attributes' => $this->attributes,
            'children' => array_map(fn($child) => $child instanceof Node ? $child->toArray() : $child, $this->children),
            'self_closing' => $this->selfClosing,
        ];

        return $array;
    }
}