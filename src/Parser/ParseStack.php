<?php

namespace Livewire\Blaze\Parser;

use Livewire\Blaze\Parser\Nodes\Node;
use Livewire\Blaze\Parser\Nodes\TagNode;
use Livewire\Blaze\Parser\Nodes\SlotNode;

class ParseStack
{
    protected array $stack = [];
    protected array $ast = [];
    
    public function addToRoot(Node $node): void
    {
        if (empty($this->stack)) {
            $this->ast[] = $node;
        } else {
            $this->getCurrentContainer()->children[] = $node;
        }
    }
    
    public function pushContainer(Node $container): void
    {
        $this->addToRoot($container);
        $this->stack[] = $container;
    }
    
    public function popContainer(): void
    {
        if (!empty($this->stack)) {
            array_pop($this->stack);
        }
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