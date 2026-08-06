<?php

namespace Livewire\Blaze\Parser\Tokens;

/**
 * Represents a native PHP block or Blade @php block.
 */
class PhpBlockToken extends Token
{
    public function __construct(
        public string $content,
    ) {}
}
