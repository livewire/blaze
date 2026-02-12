<?php

namespace Livewire\Blaze\Tokenizer\Tokens;

/**
 * Represents a closing slot tag (</x-slot>).
 */
class SlotCloseToken extends Token
{
    public function __construct(
        public ?string $name = null,
        public string $prefix = 'x-',
    ) {}
}