<?php

namespace Livewire\Blaze\Parser\Tokens;

/**
 * Represents an opening slot tag (<x-slot> or <x-slot:name>).
 */
class SlotOpenToken extends Token
{
    public function __construct(
        public ?string $name = null,
        public array $attributes = [],
        public string $slotStyle = 'standard',
        public string $prefix = 'x-',
    ) {}
}