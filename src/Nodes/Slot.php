<?php

namespace Livewire\Blaze\Nodes;

class Slot
{
    public function __construct(
        public string $name,
        public array $children,
        public ?Node $node,
    ) {}

    public function render(): string
    {
        return implode('', array_map(fn ($child) => $child->render(), $this->children));
    }
}