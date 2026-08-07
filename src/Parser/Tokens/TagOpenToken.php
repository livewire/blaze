<?php

namespace Livewire\Blaze\Parser\Tokens;

/**
 * Represents an opening or self-closing component tag.
 */
class TagOpenToken extends Token
{
    public function __construct(
        public string $prefix,
        public string $name,
        public string $attributes,
        public string $original,
        public bool $selfClosing,
    ) {}

    public function isBladeComponent()
    {
        return in_array($this->prefix, ['x-', 'x:']);
    }

    public function isSlot()
    {
        return $this->isBladeComponent() && $this->name === 'slot' || str_starts_with($this->name, 'slot:');
    }
}
