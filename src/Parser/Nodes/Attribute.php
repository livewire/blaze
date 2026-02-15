<?php

namespace Livewire\Blaze\Parser\Nodes;

/**
 * Represents a parsed attribute on a component or slot tag.
 */
class Attribute
{
    public function __construct(
        public string $name,
        public mixed $value,
        public string $propName,
        public bool $dynamic,
        public ?string $prefix = null,
        public string $quotes = '',
    ) {
    }

    /**
     * Check if this attribute is PHP-bound (prefixed with : or :$).
     */
    public function bound(): bool
    {
        return $this->prefix === ':' || $this->prefix === ':$';
    }

    /**
     * Check if the attribute value can be resolved at compile time.
     */
    public function isStaticValue(): bool
    {
        return $this->dynamic === false || in_array($this->value, ['true', 'false', 'null'], true);
    }
}
