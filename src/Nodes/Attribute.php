<?php

namespace Livewire\Blaze\Nodes;

class Attribute
{
    public function __construct(
        public string $name,
        public mixed $value,
        public bool $dynamic,
        public ?string $prefix = null,
        public string $original = '',
    ) {
    }

    public function bound(): bool
    {
        return $this->prefix === ':' || $this->prefix === ':$';
    }
}
