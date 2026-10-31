<?php

namespace Livewire\Blaze\Folder;

/**
 * Stands in for a dynamic attribute or slot while a component is rendered for folding.
 *
 * Like in Blade, a bound attribute has two copies: the raw value read by props and the escaped
 * value in the attribute bag. Each gets its own placeholder, so it's known which copy reached
 * the output without inferring it from the value. Both end with "&", which every escape turns
 * into "&amp;", so a copy the component escaped again can be told apart from an untouched one.
 *
 * Folding only restores a copy in the few forms whose runtime equivalent is known. Any other
 * form, like a copy escaped twice, merged into another value or transformed, is left in the
 * output and fails the fold, so the component is compiled without folding.
 *
 *   BLAZE_PLACEHOLDER_0_            slot or attribute that's not bound
 *   BLAZE_PLACEHOLDER_0_raw&_       bound attribute, raw copy
 *   BLAZE_PLACEHOLDER_0_raw&amp;_   bound attribute, raw copy escaped once
 *   BLAZE_PLACEHOLDER_0_bag&_       bound attribute, attribute bag copy
 */
class Placeholder
{
    /**
     * Matches a placeholder in rendered output.
     */
    public const PATTERN = 'BLAZE_PLACEHOLDER_[0-9]+_(?:(?:raw|bag)&(?:amp;)*_)?';

    /**
     * @param 'raw'|'bag'|null $copy
     */
    public function __construct(
        public int $index,
        public ?string $copy = null,
        public int $escapes = 0,
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

        if (! preg_match('/^BLAZE_PLACEHOLDER_([0-9]+)_(?:(raw|bag)&((?:amp;)*)_)?$/', $value, $matches)) {
            return null;
        }

        return new static(
            index: (int) $matches[1],
            copy: ($matches[2] ?? '') ?: null,
            escapes: substr_count($matches[3] ?? '', 'amp;'),
        );
    }

    /**
     * Get the placeholder for the attribute bag copy of a bound attribute.
     */
    public function inBag(): static
    {
        return new static($this->index, 'bag');
    }

    /**
     * Check if this is the given copy of a bound attribute, escaped the given number of times.
     */
    public function is(string $copy, int $escapes = 0): bool
    {
        return $this->copy === $copy && $this->escapes === $escapes;
    }

    public function __toString(): string
    {
        $placeholder = 'BLAZE_PLACEHOLDER_' . $this->index . '_';

        if ($this->copy) {
            $placeholder .= $this->copy . '&' . str_repeat('amp;', $this->escapes) . '_';
        }

        return $placeholder;
    }
}
