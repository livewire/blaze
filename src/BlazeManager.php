<?php

namespace Livewire\Blaze;

use Livewire\Blaze\Walker\Walker;
use Livewire\Blaze\Tokenizer\Tokenizer;
use Livewire\Blaze\Renderer\Renderer;
use Livewire\Blaze\Parser\Parser;
use Livewire\Blaze\Folder\Folder;
use Livewire\Blaze\Inspector\Inspector;

class BlazeManager
{
    public function __construct(
        protected Tokenizer $tokenizer,
        protected Parser $parser,
        protected Renderer $renderer,
        protected Walker $walker,
        protected Inspector $inspector,
        protected Folder $folder,
    ) {}

    public function compile(string $template): string
    {
        $tokens = $this->tokenizer->tokenize($template);

        $ast = $this->parser->parse($tokens);

        $ast = $this->walker->walkPre($ast, function ($node) {
            return $this->inspector->inspect($node);
        });

        $ast = $this->walker->walkPost($ast, function ($node) {
            return $this->folder->fold($node);
        });

        $output = $this->renderer->render($ast);

        return $output;
    }

    public function tokenizer(): Tokenizer
    {
        return $this->tokenizer;
    }

    public function parser(): Parser
    {
        return $this->parser;
    }

    public function renderer(): Renderer
    {
        return $this->renderer;
    }

    public function inspector(): Inspector
    {
        return $this->inspector;
    }
}
