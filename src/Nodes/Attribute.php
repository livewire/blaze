<?php

namespace Livewire\Blaze\Nodes;

class Attribute
{
    public function __construct(
        public string $name,
        public mixed $value,
        public string $propName, // camelCase
        public bool $dynamic,
        public ?string $prefix = null,
        public string $quotes = '',
    ) {
    }

    public function bound(): bool
    {
        return $this->prefix === ':' || $this->prefix === ':$';
    }

    public function isStaticValue(): bool
    {
        return $this->dynamic === false || in_array($this->value, ['true', 'false', 'null'], true);
    }
}
