<?php

namespace Livewire\Blaze\Tokenizer\Tokens;

/**
 * Represents raw text/HTML content between component tags.
 */
class TextToken extends Token
{
    public function __construct(
        public string $content,
    ) {}
}