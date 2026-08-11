<?php

namespace Livewire\Blaze\Parser\Nodes;

class DirectiveNode extends Node
{
    public function __construct(
        public string $name,
        public string $original,
        public ?string $expression = null,
    ) {
    }

    public function render(): string
    {
        return $this->original;
    }
}
