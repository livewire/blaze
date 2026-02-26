<?php

namespace Livewire\Blaze\Runtime;

use Illuminate\View\View;

/**
 * Minimal View subclass passed to Laravel view composers during Blaze component rendering.
 *
 * Extends the concrete Illuminate\View\View so that composers type-hinting
 * the concrete class (rather than the contract) receive a compatible instance.
 * Only the properties needed for composer interaction are initialised.
 */
class ComponentView extends View
{
    public function __construct(string $componentName, array $data = [])
    {
        $this->view = $componentName;
        $this->data = $data;
    }

    public function render(?callable $callback = null): string
    {
        return '';
    }
}
