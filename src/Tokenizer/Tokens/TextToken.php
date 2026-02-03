<?php

namespace Livewire\Blaze\Tokenizer\Tokens;

class TextToken extends Token
{
    public function __construct(
        public string $content,
    ) {}
}