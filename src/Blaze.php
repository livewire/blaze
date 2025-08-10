<?php

namespace Livewire\Blaze;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Livewire\Blaze\BlazeManager collectAndAppendFrontMatter(string $template, callable $callback)
 * @method static \Livewire\Blaze\BlazeManager viewContainsExpiredFrontMatter(string $view)
 * @method static \Livewire\Blaze\BlazeManager compile(string $template)
 * @method static \Livewire\Blaze\BlazeManager enable()
 * @method static \Livewire\Blaze\BlazeManager disable()
 * @method static \Livewire\Blaze\BlazeManager isEnabled()
 * @method static \Livewire\Blaze\BlazeManager isDisabled()
 * @method static \Livewire\Blaze\BlazeManager tokenizer()
 * @method static \Livewire\Blaze\BlazeManager parser()
 * @method static \Livewire\Blaze\BlazeManager walker()
 * @method static \Livewire\Blaze\BlazeManager inspector()
 * @method static \Livewire\Blaze\BlazeManager folder()
 * @see \Livewire\Blaze\BlazeManager
 */
class Blaze extends Facade
{
    public static function getFacadeAccessor()
    {
        return 'blaze';
    }
}