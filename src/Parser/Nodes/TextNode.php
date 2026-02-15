<?php

namespace Livewire\Blaze\Parser\Nodes;

/**
 * Represents raw text/HTML content in the AST.
 */
class TextNode extends Node
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