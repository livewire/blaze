<?php

namespace Livewire\Blaze\Parser\Tokens;

/**
 * Represents a closing component tag (</x-button>).
 */
class ClosingTagToken extends Token
{
    public function __construct(
        public string $name,
        public string $original,
    ) {}
}
