<?php

namespace Livewire\Blaze\Parser\Nodes;

class DirectiveNode extends TextNode
{
    public function __construct(
        public string $name,
        public string $original,
        public ?string $arguments = null,
    ) {
    }

    public function render(): string
    {
        return $this->original;
    }
}