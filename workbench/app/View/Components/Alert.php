<?php

namespace App\View\Components;

use Illuminate\View\Component;

class Alert extends Component
{
    public function render(): string
    {
        return '<div class="alert"></div>';
    }
}
