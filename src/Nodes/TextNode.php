<?php

namespace Livewire\Blaze\Nodes;

class TextNode extends Node
{
    public function __construct(
        public string $content,
    ) {}

    public function getType(): string
    {
        return 'text';
    }

    public function toArray(): array
    {
        return [
            'type' => $this->getType(),
            'content' => $this->content,
        ];
    }

    public function render(): string
    {
        return $this->content;
    }
}