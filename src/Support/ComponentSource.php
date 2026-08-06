<?php

namespace Livewire\Blaze\Support;

use Livewire\Blaze\Parser\Walker;
use Livewire\Blaze\Parser\Nodes\DirectiveNode;

class ComponentSource
{
    public string $hash;
    public Directives $directives;
    
    public function __construct(
        public string $name,
        public string $path,
        public array $ast,
    ) {
        $this->hash = Utils::hash($path);
        $this->directives = new Directives(
            (new Walker)->filter($ast, fn ($node) => $node instanceof DirectiveNode)
        );
    }
}