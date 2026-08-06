<?php

namespace Livewire\Blaze\Parser\Tokens;

/**
 * Represents an opening component tag (<x-button).
 */
class OpeningTagToken extends Token
{
    public function __construct(
        public string $name,
        public string $attributes,
        public string $original,
        public bool $selfClosing,
    ) {}
}
