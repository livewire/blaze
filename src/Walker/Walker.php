<?php

namespace Livewire\Blaze\Walker;

use Livewire\Blaze\Nodes\Node;
use Livewire\Blaze\Nodes\ComponentNode;
use Livewire\Blaze\Nodes\SlotNode;

class Walker
{
    public function walkPost(array $ast, callable $callback): array
    {
        return $this->walk($ast, $callback, true);
    }

    public function walkPre(array $ast, callable $callback): array
    {
        return $this->walk($ast, $callback, false);
    }

    public function walk(array $ast, callable $callback, bool $postOrder = false): array
    {
        $transformNode = function ($node, $tagLevel = 0) use ($callback, $postOrder, &$transformNode) {
            if (!($node instanceof Node)) return $node;

            // Pre-order traversal: transform parent before children
            if (!$postOrder) {
                $transformed = $callback($node, $tagLevel);
                if ($transformed === null) return null;
                if ($transformed !== $node) return $transformed;
            }

            // Transform children for nodes that have children
            if (($node instanceof ComponentNode || $node instanceof SlotNode) && !empty($node->children)) {
                $node->children = array_filter(
                    array_map(
                        fn($child) => $transformNode($child, $node instanceof ComponentNode ? $tagLevel + 1 : $tagLevel),
                        $node->children
                    ),
                    fn($child) => $child !== null
                );
            }

            // Post-order traversal: transform parent after children
            if ($postOrder) {
                $transformed = $callback($node, $tagLevel);
                if ($transformed === null) return null;
                return $transformed;
            }

            return $node;
        };

        return array_filter(
            array_map(fn($node) => $transformNode($node, 0), $ast),
            fn($node) => $node !== null
        );
    }
}
