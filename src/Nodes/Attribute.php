<?php

namespace Livewire\Blaze\Nodes;

class Attribute
{
    public function __construct(
        public string $name,
        public string $value,
        public bool $dynamic,
        public ?string $prefix = null,
    ) {
    }
}