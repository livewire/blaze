<?php

namespace Livewire\Blaze\Parser\Tokens;

abstract class Token
{
    abstract public function getType(): string;
    
    abstract public function toArray(): array;
}