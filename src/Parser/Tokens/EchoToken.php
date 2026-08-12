<?php

namespace Livewire\Blaze\Parser\Tokens;

class EchoToken extends Token
{
    public function __construct(
        public string $expression,
        public string $original,
    ) {}
}
