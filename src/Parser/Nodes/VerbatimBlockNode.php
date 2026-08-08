<?php

namespace Livewire\Blaze\Parser\Nodes;

/**
 * Represents a Blade @verbatim block in the AST.
 */
class VerbatimBlockNode extends Node
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
