<?php

namespace Livewire\Blaze\Parser\Tokens;

/**
 * Represents an opening or self-closing component tag.
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
