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
     * Matches the placeholder of an attribute, escaped any number of times.
     */
    public const ATTRIBUTE_PATTERN = self::PREFIX . '[0-9]+_&(?:amp;)*_';

    public function __construct(
        public readonly int $index,
        public readonly int $escapes,
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
     * Parse the placeholder of an attribute.
     */
    public static function parse(string $placeholder): self
    {
        if (! preg_match('/^' . self::PREFIX . '(?<index>[0-9]+)_(?<escapes>&(?:amp;)*)_$/', $placeholder, $matches)) {
            throw new \InvalidArgumentException("[{$placeholder}] is not the placeholder of an attribute.");
        }

        return new self((int) $matches['index'], substr_count($matches['escapes'], 'amp;'));
    }
}
