<?php

namespace Livewire\Blaze\Folder;

use Illuminate\Contracts\Translation\Translator;

/**
 * Stands in for the translator during folding and records whether anything was translated.
 *
 * Translations depend on the request locale, but folded output is compiled once and shared by
 * every locale. A component that translates while folding is therefore left unfolded. Calls
 * are passed through to the real translator, so rendering itself behaves as usual.
 */
class TranslationRecorder implements Translator
{
    protected bool $translated = false;

    public function __construct(
        protected Translator $translator,
    ) {
    }

    public function get($key, array $replace = [], $locale = null)
    {
        $this->translated = true;

        return $this->translator->get($key, $replace, $locale);
    }

    public function choice($key, $number, array $replace = [], $locale = null)
    {
        $this->translated = true;

        return $this->translator->choice($key, $number, $replace, $locale);
    }

    public function getLocale()
    {
        return $this->translator->getLocale();
    }

    public function setLocale($locale)
    {
        $this->translator->setLocale($locale);
    }

    /**
     * Whether anything was translated while this recorder stood in for the translator.
     */
    public function translated(): bool
    {
        return $this->translated;
    }

    /**
     * Other translator methods (has(), string(), ...) depend on the locale as well.
     */
    public function __call($method, $parameters)
    {
        $this->translated = true;

        return $this->translator->{$method}(...$parameters);
    }
}
