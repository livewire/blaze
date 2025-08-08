<?php

namespace Livewire\Blaze;

use Illuminate\Support\Facades\Blade;

class BladeHacker
{
    public function render(string $template): string
    {
        return Blade::render($template);
    }

    public function componentPath($name): string
    {
        // Ingest a component name. For example:
        // Blade components: <x-form.input> would be $name = 'form.input'
        // Namespaced components: <x-pages::dashboard> would be $name = 'pages::dashboard'

        // Then identify the source file path of that component and return it.

        // Use as much of Laravel's codepath as possible to identify the component path.
        // So that we don't maintain our own logic for this.
    }
}
