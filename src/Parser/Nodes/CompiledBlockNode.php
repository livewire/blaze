<?php

namespace Livewire\Blaze\Parser\Nodes;

/**
 * Represents already-compiled mixed PHP and content in the AST.
 */
class CompiledBlockNode extends Node
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
