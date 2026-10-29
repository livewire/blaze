<?php

namespace Livewire\Blaze\Folder;

use Illuminate\View\Compilers\BladeCompiler;

/**
 * Stands in for a dynamic attribute or slot while a component is rendered for folding.
 *
 * Like in Blade, a bound attribute reaches the component twice: raw in the data read by props,
 * and escaped in the attribute bag. Each copy has its own placeholder.
 *
 * The escaped copy is a plain token that escaping leaves unchanged, so it stays equal to itself
 * however often the component escapes it again, e.g. when merging it back into the bag. It's
 * always restored escaped once, like Blade escapes the bag.
 *
 * The raw copy ends with "&_&amp;_" so any escape the component applies to it leaves a trace:
 * every escape turns "&" into "&amp;", and "&amp;" only changes when the escape double encodes.
 * It's restored with as many escapes as it collected, so a value the component escaped itself
 * stays escaped. A copy transformed in any other way no longer parses and fails the fold.
 *
 *   BLAZE_PLACEHOLDER_0_                      slot or attribute that's not bound
 *   BLAZE_PLACEHOLDER_0_E_                    bound attribute, escaped copy
 *   BLAZE_PLACEHOLDER_0_&_&amp;_              bound attribute, raw copy
 *   BLAZE_PLACEHOLDER_0_&amp;_&amp;amp;_      raw copy escaped once
 *   BLAZE_PLACEHOLDER_0_&amp;_&amp;_          raw copy escaped once without double encoding
 *   BLAZE_PLACEHOLDER_0_&amp;amp;_&amp;amp;_  raw copy escaped twice, first without double encoding
 */
class Placeholder
{
    /**
     * Matches a placeholder in rendered output.
     */
    public const PATTERN = 'BLAZE_PLACEHOLDER_[0-9]+_(?:E_|&(?:amp;)*_&amp;(?:amp;)*_)?';

    public function __construct(
        public int $index,
        public bool $bound = false,
        public bool $escapedCopy = false,
        public int $escapes = 0,
        public bool $doubleEncoded = true,
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

        if (! preg_match('/^BLAZE_PLACEHOLDER_([0-9]+)_(?:(E_)|&((?:amp;)*)_&amp;((?:amp;)*)_)?$/', $value, $matches)) {
            return null;
        }

        if (($matches[2] ?? '') === 'E_') {
            return (new static((int) $matches[1]))->escaped();
        }

        $escapes = substr_count($matches[3] ?? '', 'amp;');
        $doubleEncodedEscapes = substr_count($matches[4] ?? '', 'amp;');

        if ($doubleEncodedEscapes > $escapes || $doubleEncodedEscapes < $escapes - 1) {
            return null;
        }

        return new static(
            index: (int) $matches[1],
            bound: isset($matches[3]),
            escapes: $escapes,
            doubleEncoded: $doubleEncodedEscapes === $escapes,
        );
    }

    /**
     * Check if two values are copies of the same bound attribute.
     */
    public static function same(mixed $a, mixed $b): bool
    {
        return ($a = static::parse($a)) && ($b = static::parse($b)) && $a->index === $b->index;
    }

    /**
     * Escape a value like an attribute bag does, turning the raw copy of a placeholder into its escaped copy.
     *
     * Replaces sanitizeComponentAttribute() in code compiled while folding.
     */
    public static function sanitize(mixed $value): mixed
    {
        if (($placeholder = static::parse($value)) && $placeholder->bound) {
            return (string) $placeholder->escaped();
        }

        return BladeCompiler::sanitizeComponentAttribute($value);
    }

    /**
     * Get the escaped copy of this placeholder, as the attribute bag holds it.
     */
    public function escaped(): static
    {
        return new static($this->index, bound: true, escapedCopy: true, escapes: 1);
    }

    public function __toString(): string
    {
        $placeholder = 'BLAZE_PLACEHOLDER_' . $this->index . '_';

        if ($this->escapedCopy) {
            return $placeholder . 'E_';
        }

        if ($this->bound) {
            $doubleEncodedEscapes = $this->doubleEncoded ? $this->escapes : $this->escapes - 1;

            $placeholder .= '&' . str_repeat('amp;', $this->escapes) . '_';
            $placeholder .= '&amp;' . str_repeat('amp;', $doubleEncodedEscapes) . '_';
        }

        return $placeholder;
    }
}
