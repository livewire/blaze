<?php

namespace Livewire\Blaze\Nodes;

class NamedSlotNode extends Node
{
    public function __construct(
        public string $name,
        public array $children = [],
        public string $prefix = 'x-slot',
    ) {}

    public function getType(): string
    {
        return 'named_slot';
    }

    public function toArray(): array
    {
        return [
            'type' => $this->getType(),
            'name' => $this->name,
            'prefix' => $this->prefix,
            'children' => array_map(fn($child) => $child instanceof Node ? $child->toArray() : $child, $this->children),
        ];
    }

    public function render(): string
    {
        $output = "<{$this->prefix} name=\"{$this->name}\">";
        foreach ($this->children as $child) {
            $output .= $child instanceof Node ? $child->render() : (string) $child;
        }
        $output .= "</{$this->prefix}>";
        return $output;
    }
}
