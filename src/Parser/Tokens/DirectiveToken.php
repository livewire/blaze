<?php

namespace Livewire\Blaze\Parser\Tokens;

class DirectiveToken extends Token
{
    public function __construct(
        public string $name,
        public string $original,
        public ?string $expression = null,
    ) {}
}
