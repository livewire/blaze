<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\Contracts\View\View;

class Alert extends Component
{
    public function __construct(public string $message = '') {}

    public function render(): View
    {
        return view('components.alert');
    }
}
