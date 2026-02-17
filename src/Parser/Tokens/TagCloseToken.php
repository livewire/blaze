<?php

namespace Livewire\Blaze\Parser\Tokens;

/**
 * Represents a closing component tag (</x-button>).
 */
class TagCloseToken extends Token
{
    public function __construct(
        public string $name,
        public string $prefix,
        public string $namespace = '',
    ) {}
}