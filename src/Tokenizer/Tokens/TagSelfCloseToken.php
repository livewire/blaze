<?php

namespace Livewire\Blaze\Tokenizer\Tokens;

class TagSelfCloseToken extends Token
{
    public function __construct(
        public string $name,
        public string $prefix,
        public string $namespace = '',
        public string $attributes = '',
    ) {}
}