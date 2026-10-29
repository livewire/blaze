<?php

namespace Livewire\Blaze\Folder;

/**
 * Stands in for a dynamic attribute or slot while a component is rendered for folding.
 *
 * Like in Blade, a bound value is escaped in the attribute bag but not in the data read by props,
 * and the component can escape either copy again. Escaping a plain placeholder wouldn't change it,
 * so the placeholder of a bound attribute ends with "&_&amp;_". Every escape encodes the bare "&",
 * but only one that double encodes, like the attribute bag's, encodes the "&amp;" again; an echo
 * under Blade::withoutDoubleEncoding() leaves it alone. Counting the entities of each tells how
 * many times the value was escaped while rendering and whether it was escaped without double
 * encoding first, whatever escaped it, so it can be restored the same way. A placeholder
 * transformed in any other way no longer parses and fails the fold.
 *
 *   BLAZE_PLACEHOLDER_0_                      slot or attribute that's not bound
 *   BLAZE_PLACEHOLDER_0_&_&amp;_              bound attribute, raw
 *   BLAZE_PLACEHOLDER_0_&amp;_&amp;amp;_      bound attribute, escaped once
 *   BLAZE_PLACEHOLDER_0_&amp;amp;_&amp;amp;amp;_  bound attribute, escaped twice
 *   BLAZE_PLACEHOLDER_0_&amp;_&amp;_          bound attribute, escaped once without double encoding
 *   BLAZE_PLACEHOLDER_0_&amp;amp;_&amp;amp;_  bound attribute, escaped without double encoding, then once more
 */
class Placeholder
{
    /**
     * Matches a placeholder in rendered output.
     */
    public const PATTERN = 'BLAZE_PLACEHOLDER_[0-9]+_(?:&(?:amp;)*_&(?:amp;)+_)?';

    public function __construct(
        public int $index,
        public bool $bound = false,
        public int $escapes = 0,
        public bool $escapedWithoutDoubleEncoding = false,
    ) {
    }

    /**
     * Parse a placeholder, or return null when the value isn't one.
     */
    public static function parse(mixed $value): ?static
    {
        if (! is_string($value) || ! str_starts_with($value, 'BLAZE_PLACEHOLDER_')) {
            return null;
        }

        if (! preg_match('/^BLAZE_PLACEHOLDER_([0-9]+)_(?:&((?:amp;)*)_&((?:amp;)+)_)?$/', $value, $matches)) {
            return null;
        }

        if (! isset($matches[2])) {
            return new static(index: (int) $matches[1]);
        }

        // Escaping without double encoding after any other escape changes nothing, so the bare "&"
        // records one entity more than the "&amp;" exactly when the value was escaped that way first
        $escapes = substr_count($matches[3], 'amp;') - 1;
        $escapedWithoutDoubleEncoding = substr_count($matches[2], 'amp;') - $escapes;

        if ($escapedWithoutDoubleEncoding !== 0 && $escapedWithoutDoubleEncoding !== 1) {
            return null;
        }

        return new static(
            index: (int) $matches[1],
            bound: true,
            escapes: $escapes,
            escapedWithoutDoubleEncoding: (bool) $escapedWithoutDoubleEncoding,
        );
    }

    /**
     * Check if two values are copies of the same bound attribute.
     */
    public static function same(mixed $a, mixed $b): bool
    {
        return ($a = static::parse($a)) && ($b = static::parse($b)) && $a->index === $b->index;
    }

    public function __toString(): string
    {
        $placeholder = 'BLAZE_PLACEHOLDER_' . $this->index . '_';

        if ($this->bound) {
            $placeholder .= '&' . str_repeat('amp;', $this->escapes + (int) $this->escapedWithoutDoubleEncoding) . '_';
            $placeholder .= '&' . str_repeat('amp;', $this->escapes + 1) . '_';
        }

        return $placeholder;
    }
}
