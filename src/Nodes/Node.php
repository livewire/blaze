<?php

namespace Livewire\Blaze\Nodes;

abstract class Node
{
    abstract public function render(): string;
}