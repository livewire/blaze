<?php

namespace Livewire\Blaze\Walker;

use Livewire\Blaze\Nodes\ComponentNode;
use Livewire\Blaze\Nodes\SlotNode;

class Walker
{
    public function walk(array $nodes, callable $preCallback, callable $postCallback): array
    {
        $result = [];

        foreach ($nodes as $node) {
            $processed = $preCallback($node);

            if (($node instanceof ComponentNode || $node instanceof SlotNode) && !empty($node->children)) {
                $node->children = $this->walk($node->children, $preCallback, $postCallback);
            }

            $processed = $postCallback($node);

            $result[] = $processed ?? $node;
        }

        return $result;
    }
}
