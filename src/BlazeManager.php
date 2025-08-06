<?php

namespace Livewire\Blaze;

use Livewire\Blaze\Compiler\Compiler;

class BlazeManager
{
    public function __construct(
        protected Compiler $compiler,
    ) {}

    public function compile(string $template): string
    {
        return $this->compiler->compile($template);
    }

    public function compiler(): Compiler
    {
        return $this->compiler;
    }
}
