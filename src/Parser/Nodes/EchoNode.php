<?php

namespace Livewire\Blaze\Parser\Nodes;

/**
 * Represents an executable Blade echo expression.
 */
class EchoNode extends Node
{
    public function __construct(
        public string $expression,
        public string $original,
    ) {}

    /** {@inheritdoc} */
    public function render(): string
    {
        return $this->original;
    }
}
