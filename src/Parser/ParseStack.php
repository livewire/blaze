<?php

namespace Livewire\Blaze\Parser;

use Livewire\Blaze\Nodes\ComponentNode;
use Livewire\Blaze\Nodes\SlotNode;
use Livewire\Blaze\Nodes\Node;

class ParseStack
{
    protected array $stack = [];

    protected array $ast = [];

    public function addToRoot(Node $node): void
    {
        if (empty($this->stack)) {
            $this->ast[] = $node;
        } else {
            $current = $this->getCurrentContainer();

            if ($current instanceof ComponentNode || $current instanceof SlotNode) {
                $current->children[] = $node;
            } else {
                // Fallback: if current container cannot accept children, append to root...
                $this->ast[] = $node;
            }
        }
    }

    public function pushContainer(Node $container): void
    {
        $this->addToRoot($container);

        $this->stack[] = $container;
    }

    public function popContainer(): ?Node
    {
        if (! empty($this->stack)) {
            return array_pop($this->stack);
        }

        return null;
    }

    public function getCurrentContainer(): ?Node
    {
        return empty($this->stack) ? null : end($this->stack);
    }

    public function getAst(): array
    {
        return $this->ast;
    }

    public function isEmpty(): bool
    {
        return empty($this->stack);
    }

    public function depth(): int
    {
        return count($this->stack);
    }
}