<?php

namespace Livewire\Blaze\Folder;

/**
 * Placeholders stand in for dynamic attributes and slots while a component is rendered for folding.
 *
 * Placeholders of attributes end with "&_". Like any string, escaping turns the "&" into "&amp;",
 * so the placeholder shows how many times the value it stands for was escaped:
 *
 *   BLAZE_PLACEHOLDER_0_         slot
 *   BLAZE_PLACEHOLDER_0_&_       attribute, raw like in props
 *   BLAZE_PLACEHOLDER_0_&amp;_   attribute, escaped once like bound attributes in the attribute bag
 */
class Placeholder
{
    public const PREFIX = 'BLAZE_PLACEHOLDER_';

    /**
     * Matches a placeholder, capturing its index and, for an attribute, the suffix.
     */
    public const PATTERN = self::PREFIX . '(?<index>[0-9]+)_(?<suffix>&(?:amp;)*_)?';

    /**
     * @param  int|null  $escapes  How many times the attribute was escaped, null for slots
     */
    public function __construct(
        public readonly int $index,
        public readonly ?int $escapes,
    ) {
    }

    /**
     * Create the placeholder of an attribute.
     */
    public static function attribute(int $index): string
    {
        return self::PREFIX . $index . '_&_';
    }

    /**
     * Create the placeholder of a slot.
     */
    public static function slot(int $index): string
    {
        return self::PREFIX . $index . '_';
    }

    /**
     * Create from the matches of a pattern that includes PATTERN.
     */
    public static function fromMatch(array $matches): self
    {
        $suffix = $matches['suffix'] ?? '';

        return new self((int) $matches['index'], $suffix === '' ? null : substr_count($suffix, 'amp;'));
    }
}
