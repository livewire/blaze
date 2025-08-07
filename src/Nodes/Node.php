<?php

namespace Livewire\Blaze\Nodes;

abstract class Node
{
    abstract public function getType(): string;
    
    abstract public function toArray(): array;
}