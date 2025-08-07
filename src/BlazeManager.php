<?php

namespace Livewire\Blaze;

use Livewire\Blaze\Compiler\Compiler;
use Livewire\Blaze\Parser\Parser;

class BlazeManager
{
    public function __construct(
        protected Compiler $compiler,
        protected Parser $parser,
    ) {}

    public function compile(string $template): string
    {
        return $this->parser->parse($template, function ($ast) {
            return $ast;
        });
    }

    public function compiler(): Compiler
    {
        return $this->compiler;
    }

    public function parser(): Parser
    {
        return $this->parser;
    }
}
