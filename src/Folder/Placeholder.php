<?php

namespace Livewire\Blaze\Folder;

/**
 * Stands in for a dynamic attribute or slot while a component is rendered for folding.
 *
 * Like in Blade, a bound value is escaped in the attribute bag but not in the data read by props,
 * and the component can escape either copy again. Escaping a plain placeholder wouldn't change it,
 * so the placeholder of a bound attribute ends with "&_", which every escape turns into "&amp;_",
 * "&amp;amp;_" and so on. Counting them tells how many times the value was escaped while rendering,
 * whatever escaped it, so it can be restored the same way.
 *
 * It's followed by "&amp;_", which only changes when an escape double encodes. One less "amp;"
 * there means the first escape didn't double encode, any later escape that doesn't changes nothing.
 *
 *   BLAZE_PLACEHOLDER_0_                      slot or attribute that's not bound
 *   BLAZE_PLACEHOLDER_0_&_&amp;_              bound attribute, raw
 *   BLAZE_PLACEHOLDER_0_&amp;_&amp;amp;_      bound attribute, escaped once
 *   BLAZE_PLACEHOLDER_0_&amp;_&amp;_          bound attribute, escaped once without double encoding
 *   BLAZE_PLACEHOLDER_0_&amp;amp;_&amp;amp;_  bound attribute, escaped twice, first without double encoding
 */
class Placeholder
{
    /**
     * Matches a placeholder in rendered output.
     */
    public const PATTERN = 'BLAZE_PLACEHOLDER_[0-9]+_(?:&(?:amp;)*_&amp;(?:amp;)*_)?';

    public function __construct(
        public int $index,
        public bool $bound = false,
        public int $escapes = 0,
        public bool $doubleEncoded = true,
    ) {
    }

    /**
     * Parse a placeholder, or return null when the value isn't one.
     */
    public static function parse(mixed $value): ?static
    {
        if (! is_string($value) || ! preg_match('/^BLAZE_PLACEHOLDER_([0-9]+)_(?:&((?:amp;)*)_&amp;((?:amp;)*)_)?$/', $value, $matches)) {
            return null;
        }

        $escapes = substr_count($matches[2] ?? '', 'amp;');
        $doubleEncodedEscapes = substr_count($matches[3] ?? '', 'amp;');

        if ($doubleEncodedEscapes > $escapes || $doubleEncodedEscapes < $escapes - 1) {
            return null;
        }

        return new static(
            index: (int) $matches[1],
            bound: isset($matches[2]),
            escapes: $escapes,
            doubleEncoded: $doubleEncodedEscapes === $escapes,
        );
    }

    public function __toString(): string
    {
        $placeholder = 'BLAZE_PLACEHOLDER_' . $this->index . '_';

        if ($this->bound) {
            $doubleEncodedEscapes = $this->doubleEncoded ? $this->escapes : $this->escapes - 1;

            $placeholder .= '&' . str_repeat('amp;', $this->escapes) . '_';
            $placeholder .= '&amp;' . str_repeat('amp;', $doubleEncodedEscapes) . '_';
        }

        return $placeholder;
    }
}
