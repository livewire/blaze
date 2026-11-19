<?php

namespace Livewire\Blaze\Exceptions;

/**
 * Thrown when a component translated text while being folded.
 *
 * Translations depend on the request locale, while folded output is compiled once and shared by
 * every locale. Translating is not a misuse of folding, so the component is left unfolded
 * rather than reported, even in throw mode.
 */
class TranslatedWhileFoldingException extends \Exception
{
    public function __construct(string $componentPath)
    {
        parent::__construct("Component '{$componentPath}' translates text and can't be folded");
    }
}
