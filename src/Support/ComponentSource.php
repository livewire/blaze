<?php

namespace Livewire\Blaze\Support;

use Livewire\Blaze\Parser\Template;

class ComponentSource
{
    public string $hash;
    
    public function __construct(
        public string $name,
        public string $path,
        public Template $template,
    ) {
        $this->hash = Utils::hash($path);
    }
}