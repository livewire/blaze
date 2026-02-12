<?php

namespace Livewire\Blaze\Nodes;

/**
 * Base class for all AST nodes in the Blaze pipeline.
 */
abstract class Node
{
    /**
     * Render this node to its string output.
     */
    abstract public function render(): string;
}