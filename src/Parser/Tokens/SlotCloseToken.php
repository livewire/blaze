<?php

namespace Livewire\Blaze\Parser\Tokens;

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