<?php

namespace Livewire\Blaze\Nodes;

class TextNode extends Node
{
    public function __construct(
        public string $content,
    ) {}

    public function render(): string
    {
        return $this->content;
    }
}