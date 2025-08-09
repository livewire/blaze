<?php

namespace Livewire\Blaze\Nodes;

class DefaultSlotNode extends Node
{
    public function __construct(
        public array $children = [],
    ) {}

    public function getType(): string
    {
        return 'default_slot';
    }

    public function toArray(): array
    {
        return [
            'type' => $this->getType(),
            'children' => array_map(fn($child) => $child instanceof Node ? $child->toArray() : $child, $this->children),
        ];
    }

    public function render(): string
    {
        $output = '';
        foreach ($this->children as $child) {
            $output .= $child instanceof Node ? $child->render() : (string) $child;
        }
        return $output;
    }
}