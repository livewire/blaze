<?php

namespace Livewire\Blaze\Parser;

use Livewire\Blaze\Parser\Nodes\ComponentNode;
use Livewire\Blaze\Parser\Nodes\SlotNode;

/**
 * Depth-first tree walker that applies pre/post callbacks to each AST node.
 */
class Walker
{
    /**
     * Walk the AST, applying pre-callback before children and post-callback after.
     */
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
