<?php

namespace Livewire\Blaze\Walker;

use Livewire\Blaze\Nodes\ComponentNode;
use Livewire\Blaze\Nodes\SlotNode;

class Walker
{
    public function walkPost(array $nodes, callable $callback): array
    {
        $result = [];

        foreach ($nodes as $node) {
            // Recurse into children for relevant container nodes
            if (($node instanceof ComponentNode || $node instanceof SlotNode) && !empty($node->children)) {
                $node->children = $this->walkPost($node->children, $callback);
            }

            $processed = $callback($node);

            $result[] = $processed ?? $node;
        }

        return $result;
    }
}
