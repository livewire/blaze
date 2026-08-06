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
            $node = $preCallback($node) ?? $node;

            if (($node instanceof ComponentNode || $node instanceof SlotNode) && !empty($node->children)) {
                $node->children = $this->walk($node->children, $preCallback, $postCallback);
            }

            $node = $postCallback($node) ?? $node;

            $result[] = $node;
        }

        return $result;
    }

    /**
     * @return \Generator<int, \Livewire\Blaze\Parser\Nodes\Node>
     */
    public function iterate(array $nodes): \Generator
    {
        foreach ($nodes as $node) {
            yield spl_object_id($node) => $node;

            if (($node instanceof ComponentNode || $node instanceof SlotNode) && $node->children) {
                yield from $this->iterate($node->children);
            }
        }
    }

    public function filter(array $nodes, callable $predicate): array
    {
        return iterator_to_array((function () use ($nodes, $predicate) {
            foreach ($this->iterate($nodes) as $key => $value) {
                if ($predicate($value)) {
                    yield $key => $value;
                }
            }
        })());
    }
}
