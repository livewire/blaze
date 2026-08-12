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
    
    public function is(string $name): bool
    {
        return strtolower($this->name) === strtolower($name);
    }
}
