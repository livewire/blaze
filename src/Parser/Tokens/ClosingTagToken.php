<?php

namespace Livewire\Blaze\Parser\Tokens;

/**
 * Represents a closing component tag (/>).
 */
class ClosingTagToken extends Token
{
    public function __construct(
        public string $name,
        public string $original,
    ) {}
}
