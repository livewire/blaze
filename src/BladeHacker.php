<?php

namespace Livewire\Blaze;

use Illuminate\Support\Facades\Blade;

class BladeHacker
{
    public function render(string $template): string
    {
        return Blade::render($template);
    }
}
