<?php

namespace App\View\Components\Bench\Blaze;

use App\View\Components\Alert as BaseAlert;

class Alert extends BaseAlert
{
    public function render()
    {
        return view('components.bench.blaze.alert');
    }
}