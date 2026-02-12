<?php

namespace Livewire\Blaze\Tokenizer\Tokens;

/**
 * Represents an opening component tag (<x-button ...>).
 */
class TagOpenToken extends Token
{
    public function __construct(
        public string $name,
        public string $prefix,
        public string $namespace = '',
        public string $attributes = '',
    ) {}
}