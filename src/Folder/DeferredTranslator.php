<?php

namespace Livewire\Blaze\Folder;

use Illuminate\Contracts\Translation\Translator;
use Livewire\Blaze\Exceptions\InvalidBlazeFoldUsageException;

/**
 * Stands in for the translator during folding so translations are resolved at runtime.
 *
 * Translation calls return placeholders that are restored into runtime translator calls after
 * rendering. Placeholders contain "&" so the number of times they were escaped can be
 * detected from the output ("&", "&amp;", "&amp;amp;", ...) and applied at runtime.
 *
 * Placeholders also mix letter cases and contain a backslash so common transformations
 * (case changes, JSON encoding) leave them unrecognizable, which aborts folding.
 */
class DeferredTranslator implements Translator
{
    protected static int $placeholderIndex = 0;

    protected array $expressions = [];

    public function __construct(
        protected Translator $translator,
        protected string $path,
    ) {
    }

    public function get($key, array $replace = [], $locale = null)
    {
        return $this->defer('get', func_get_args(), required: 1);
    }

    public function choice($key, $number, array $replace = [], $locale = null)
    {
        return $this->defer('choice', func_get_args(), required: 2);
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
     * Other translator methods (has(), string(), array(), ...) depend on the locale and can't be deferred.
     */
    public function __call($method, $parameters)
    {
        throw InvalidBlazeFoldUsageException::forTranslation($this->path);
    }

    /**
     * Replace translation placeholders in rendered output with runtime translator calls.
     */
    public function restore(string $html): string
    {
        if (! $this->expressions) {
            return $html;
        }

        // Placeholders inside PHP blocks (e.g. @unblaze scope) are not supported...
        if (preg_match('/<\?php(?:(?!\?>).)*blaze_translation_/si', $html)) {
            throw InvalidBlazeFoldUsageException::forTranslation($this->path);
        }

        $html = preg_replace_callback('/blaze_translation_(\d+)_T\\\\&((?:amp;)*)_/', function ($matches) {
            $expression = $this->expressions[$matches[1]];
            $escapes = substr_count($matches[2], 'amp;');

            if ($escapes === 0) {
                return '{!! ' . $expression . ' !!}';
            }

            return '{{ ' . str_repeat('e(', $escapes - 1) . $expression . str_repeat(')', $escapes - 1) . ' }}';
        }, $html);

        // Remaining placeholders were transformed and can't be restored...
        if (stripos($html, 'blaze_translation_') !== false) {
            throw InvalidBlazeFoldUsageException::forTranslation($this->path);
        }

        return $html;
    }

    /**
     * Store the runtime expression for a translator call and return its placeholder.
     */
    protected function defer(string $method, array $arguments, int $required): string
    {
        array_walk_recursive($arguments, function ($value) {
            if (! is_scalar($value) && ! is_null($value) || str_contains((string) $value, 'BLAZE_PLACEHOLDER_')) {
                throw InvalidBlazeFoldUsageException::forTranslation($this->path);
            }
        });

        // Drop trailing default arguments passed by helpers like __() to keep compiled output readable...
        while (count($arguments) > $required && in_array(end($arguments), [null, []], true)) {
            array_pop($arguments);
        }

        $index = static::$placeholderIndex++;

        $this->expressions[$index] = "app('translator')->{$method}(" . implode(', ', array_map(fn ($argument) => var_export($argument, true), $arguments)) . ')';

        return 'blaze_translation_' . $index . '_T\\&_';
    }
}
