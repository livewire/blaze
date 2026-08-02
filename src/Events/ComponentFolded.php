<?php

namespace Livewire\Blaze\Events;

/**
 * Dispatched when a component is successfully folded into its parent template.
 */
class ComponentFolded
{
    public function __construct(
        public string $name,
        public string $path,
        public int $filemtime
    ) {}
}