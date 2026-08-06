<?php

namespace Livewire\Blaze\Parser;

use Livewire\Blaze\Support\Directives;
use Livewire\Blaze\Parser\Nodes\Node;

class Template
{
    public Directives $directives;

    public function __construct(
        public array $nodes,
    ) {
        $this->directives = new Directives(
            (new Walker)->filter($nodes, function (Node $node) {
                return $node->isDirective(['blaze', 'aware', 'props']);
            })
        );
    }
}