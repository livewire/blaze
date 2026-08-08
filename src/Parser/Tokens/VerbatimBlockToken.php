<?php

namespace Livewire\Blaze\Parser\Tokens;

/**
 * Represents a Blade @verbatim block.
 */
class VerbatimBlockToken extends Token
{
    public function __construct(
        public string $content,
    ) {}
}
