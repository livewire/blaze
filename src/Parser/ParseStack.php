<?php

namespace Livewire\Blaze\Parser;

use Livewire\Blaze\Parser\Nodes\ComponentNode;
use Livewire\Blaze\Parser\Nodes\SlotNode;
use Livewire\Blaze\Parser\Nodes\Node;

/**
 * Maintains the nesting stack during parsing, building the AST as tokens are processed.
 */
class ParseStack
{
    protected array $stack = [];

    protected array $ast = [];

    /**
     * Add a node as a child of the current container, or to the root if no container is open.
     */
    public function addToRoot(Node $node): void
    {
        if (empty($this->stack)) {
            $this->ast[] = $node;
        } else {
            $current = $this->getCurrentContainer();

            if ($current instanceof ComponentNode || $current instanceof SlotNode) {
                $current->children[] = $node;
            } else {
            $this->ast[] = $node;
            }
        }
    }

    /**
     * Push a container node onto the stack and add it to the current parent.
     */
    public function pushContainer(Node $container): void
    {
        $this->addToRoot($container);

        $this->stack[] = $container;
    }

    /**
     * Pop the current container off the stack.
     */
    public function popContainer(): ?Node
    {
        if (! empty($this->stack)) {
            return array_pop($this->stack);
        }

        return null;
    }

    /**
     * Get the current innermost container node.
     */
    public function getCurrentContainer(): ?Node
    {
        return empty($this->stack) ? null : end($this->stack);
    }

    /**
     * Get the completed AST (root-level nodes).
     */
    public function getAst(): array
    {
        return $this->ast;
    }

    /**
     * Check if the nesting stack is empty.
     */
    public function isEmpty(): bool
    {
        return empty($this->stack);
    }

    /**
     * Get the current nesting depth.
     */
    public function depth(): int
    {
        return count($this->stack);
    }
}