<?php

namespace Livewire\Blaze\Parser\Tokens;

/**
 * Represents raw text/HTML content between component tags.
 */
class PhpBlockToken extends Token
{
    public function __construct(
        public string $content,
    ) {}
}
