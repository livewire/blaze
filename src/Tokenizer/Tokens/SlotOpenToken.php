<?php

namespace Livewire\Blaze\Tokenizer\Tokens;

/**
 * Represents an opening slot tag (<x-slot> or <x-slot:name>).
 */
class SlotOpenToken extends Token
{
    public function __construct(
        public ?string $name = null,
        public string $attributes = '',
        public string $slotStyle = 'standard',
        public string $prefix = 'x-',
    ) {}
}