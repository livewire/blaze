<?php

namespace Livewire\Blaze\Tokenizer\Tokens;

class SlotCloseToken extends Token
{
    public function __construct(
        public ?string $name = null,
        public string $prefix = 'x-',
    ) {}
}