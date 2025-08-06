<?php

namespace Livewire\Blaze;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Livewire\Blaze\Compiler\Compiler
 */
class Blaze extends Facade
{
    public static function getFacadeAccessor()
    {
        return 'blaze';
    }
}