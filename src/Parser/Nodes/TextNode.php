<?php

namespace Livewire\Blaze\Parser\Nodes;

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
}