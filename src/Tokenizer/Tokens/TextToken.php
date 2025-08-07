<?php

namespace Livewire\Blaze\Tokenizer\Tokens;

class TextToken extends Token
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