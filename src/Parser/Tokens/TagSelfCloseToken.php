<?php

namespace Livewire\Blaze\Parser\Tokens;

/**
 * Represents a self-closing component tag (<x-button ... />).
 */
class TagSelfCloseToken extends Token
{
    public function __construct(
        public string $name,
        public string $prefix,
        public string $namespace = '',
        public array $attributes = [],
    ) {}
}