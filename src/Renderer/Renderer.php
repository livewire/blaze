<?php

namespace Livewire\Blaze\Renderer;

use Livewire\Blaze\Nodes\Node;

class Renderer
{
    public function render(array $ast): string
    {
        return implode('', array_map(function (Node $node) {
            return $node->render();
        }, $ast));
    }
}