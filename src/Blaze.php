<?php

namespace Livewire\Blaze;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string collectAndAppendFrontMatter(string $template, callable $callback)
 * @method static bool viewContainsExpiredFrontMatter(\Illuminate\View\View $view)
 * @method static string compile(string $template)
 * @method static string render(array $nodes)
 * @method static void enable()
 * @method static void disable()
 * @method static bool isEnabled()
 * @method static bool isDisabled()
 * @method static \Livewire\Blaze\Tokenizer\Tokenizer tokenizer()
 * @method static \Livewire\Blaze\Parser\Parser parser()
 * @method static \Livewire\Blaze\Folder\Folder folder()
 * @method static array flushFoldedEvents()
 * @method static \Livewire\Blaze\BlazeConfig optimize()
 *
 * @see \Livewire\Blaze\BlazeManager
 */
class Blaze extends Facade
{
    public static function getFacadeAccessor()
    {
        return 'blaze';
    }
}
