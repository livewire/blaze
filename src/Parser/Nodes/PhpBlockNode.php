<?php

namespace Livewire\Blaze\Parser\Nodes;

/**
 * Represents a native PHP block or Blade @php block in the AST.
 */
class PhpBlockNode extends Node
{
    public function __construct(
        public string $content,
    ) {}

    /** {@inheritdoc} */
    public function render(): string
    {
        return $this->content;
    }
}
