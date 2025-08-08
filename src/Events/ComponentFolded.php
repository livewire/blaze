<?php

namespace Livewire\Blaze\Events;

class ComponentFolded
{
    public function __construct(
        public string $name,
        public string $path,
        public int $filemtime
    ) {}
}