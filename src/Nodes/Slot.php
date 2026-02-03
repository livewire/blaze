<?php

namespace Livewire\Blaze\Nodes;

class Slot
{
    public function __construct(
        public string $name,
        public array $children,
        public ?Node $node,
    ) {}
}