<?php

namespace Livewire\Blaze\Compiler;

use Livewire\Blaze\Parser\Parser;

class Compiler
{
    public function __construct(
        protected Parser $parser,
    ) {}

    public function compile(string $template): string
    {
        $tokens = $this->parser->tokenize($template);

        $ast = $this->parser->parse($tokens);

        $ast = $this->parser->transform($ast, function ($node) {
            return $node;
        });

        return $this->parser->render($ast);
    }
}
