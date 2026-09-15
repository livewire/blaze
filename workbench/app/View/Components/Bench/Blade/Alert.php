<?php

namespace App\View\Components\Bench\Blade;

use Illuminate\View\Component;

class Alert extends Component
{
    public function __construct(
        public string $message,
    ) {}

    public function render()
    {
        return view('components.bench.blade.alert');
    }
}
