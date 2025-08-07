<?php

namespace Livewire\Blaze\Tokenizer\Tokens;

abstract class Token
{
    abstract public function getType(): string;
    
    abstract public function toArray(): array;
}