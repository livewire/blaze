<?php

namespace Livewire\Blaze\Folder;

/**
 * Placeholders stand in for dynamic attributes and slots while a component is rendered for folding.
 *
 * Placeholders of bound attributes end with "&_". Like any string, escaping turns the "&" into "&amp;",
 * so the placeholder shows how many times the value it stands for was escaped:
 *
 *   BLAZE_PLACEHOLDER_0_         slot or attribute that's not bound
 *   BLAZE_PLACEHOLDER_0_&_       bound attribute, raw like in props
 *   BLAZE_PLACEHOLDER_0_&amp;_   bound attribute, escaped once like in the attribute bag
 */
class Placeholder
{
    /**
     * Matches a placeholder, capturing its index and, for a bound attribute, the "amp;" of each escape.
     */
    public const PATTERN = 'BLAZE_PLACEHOLDER_([0-9]+)_(?:&((?:amp;)*)_)?';

    /**
     * Create the placeholder of a slot or attribute.
     */
    public static function make(int $index, bool $bound = false): string
    {
        return 'BLAZE_PLACEHOLDER_' . $index . '_' . ($bound ? '&_' : '');
    }

    /**
     * Parse a value that is exactly one placeholder into its index and escapes, which are null when it's not bound.
     *
     * @return array{0: int, 1: ?int}|null
     */
    public static function parse(mixed $value): ?array
    {
        if (! is_string($value) || ! preg_match('/^' . static::PATTERN . '$/', $value, $matches, PREG_UNMATCHED_AS_NULL)) {
            return null;
        }

        return [(int) $matches[1], isset($matches[2]) ? substr_count($matches[2], 'amp;') : null];
    }
}
