<?php

namespace App\View\Components;

use Illuminate\View\Component;

class Alert extends Component
{
    public function __construct(
        public string $message,
    ) {}

    public function render()
    {
        return view('components.alert');
    }
}
