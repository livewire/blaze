<?php

namespace Livewire\Blaze\Support;

class AttributeParser
{
    public function parseAndReplaceDynamics(
        string $attributesString,
        array &$attributePlaceholders,
        array &$attributeNameToPlaceholder
    ): string {
        // Early exit unless bound syntax or echo present (support start or whitespace before colon)...
        $hasBound = (bool) preg_match('/(^|\s):[A-Za-z$]/', $attributesString);
        if (! $hasBound && strpos($attributesString, '{{') === false) {
            return $attributesString;
        }

        // :name="..."
        $attributesString = preg_replace_callback('/(?<!\S)(\s*):([A-Za-z0-9_:-]+)\s*=\s*"([^"]*)"/', function ($matches) use (&$attributePlaceholders, &$attributeNameToPlaceholder) {
            $whitespace = $matches[1];
            $attributeName = $matches[2];
            $attributeValue = $matches[3];

            $placeholder = 'ATTR_PLACEHOLDER_' . count($attributePlaceholders);
            $attributePlaceholders[$placeholder] = '{{ ' . $attributeValue . ' }}';
            $attributeNameToPlaceholder[$attributeName] = $placeholder;

            return $whitespace . $attributeName . '="' . $placeholder . '"';
        }, $attributesString);

        // Short :$var
        $attributesString = preg_replace_callback('/(\s*):\$([a-zA-Z0-9_]+)/', function ($matches) use (&$attributePlaceholders, &$attributeNameToPlaceholder) {
            $whitespace = $matches[1];
            $variableName = $matches[2];

            $placeholder = 'ATTR_PLACEHOLDER_' . count($attributePlaceholders);
            $attributePlaceholders[$placeholder] = '{{ $' . $variableName . ' }}';
            $attributeNameToPlaceholder[$variableName] = $placeholder;

            return $whitespace . $variableName . '="' . $placeholder . '"';
        }, $attributesString);

        // Echoes inside quoted attribute values: foo="{{ $bar }}" or foo="{{ $bar }}-{{ $baz }}"
        // Captures the entire attribute value when it contains any {{ }} echoes
        $attributesString = preg_replace_callback('/(\s*)([a-zA-Z0-9_:-]+)\s*=\s*"((?:[^"]*\{\{.+?\}\}[^"]*)+)"/', function ($matches) use (&$attributePlaceholders, &$attributeNameToPlaceholder) {
            $whitespace = $matches[1];
            $attributeName = $matches[2];
            $attributeValue = $matches[3];

            $placeholder = 'ATTR_PLACEHOLDER_' . count($attributePlaceholders);
            $attributePlaceholders[$placeholder] = $attributeValue;
            $attributeNameToPlaceholder[$attributeName] = $placeholder;

            return $whitespace . $attributeName . '="' . $placeholder . '"';
        }, $attributesString);

        return $attributesString;
    }

    /**
     * Parse an attribute string into an array of attributes.
     *
     * For example, the string:
     * `foo="bar" :name="$name" :$baz searchable`
     *
     * will be parsed into the array:
     * [
     *     'foo' => [
     *         'isDynamic' => false,
     *         'value' => 'bar',
     *         'original' => 'foo="bar"',
     *     ],
     *     'name' => [
     *         'isDynamic' => true,
     *         'value' => '$name',
     *         'original' => ':name="$name"',
     *     ],
     *     'baz' => [
     *         'isDynamic' => true,
     *         'value' => '$baz',
     *         'original' => ':$baz',
     *     ],
     *     'searchable' => [
     *         'isDynamic' => false,
     *         'value' => true,
     *         'original' => 'searchable',
     *     ],
     * ]
     */
    public function parseAttributeStringToArray(string $attributesString): array
    {
        $attributes = [];

        // Handle :name="..." syntax
        preg_match_all('/(?:^|\s):([A-Za-z0-9_:-]+)\s*=\s*"([^"]*)"/', $attributesString, $matches, PREG_SET_ORDER);
        foreach ($matches as $m) {
            $attributeName = str($m[1])->camel()->toString();
            if (isset($attributes[$attributeName])) continue;

            $attributes[$attributeName] = [
                'isDynamic' => true,
                'value' => $m[2],
                'original' => trim($m[0]),
            ];
        }

        // Handle short :$var syntax (expands to :var="$var")
        preg_match_all('/(?:^|\s):\$([A-Za-z0-9_:-]+)(?=\s|$)/', $attributesString, $matches, PREG_SET_ORDER);
        foreach ($matches as $m) {
            $raw = $m[1];
            $attributeName = str($raw)->camel()->toString();
            if (isset($attributes[$attributeName])) continue;

            $attributes[$attributeName] = [
                'isDynamic' => true,
                'value' => '$' . $raw,
                'original' => trim($m[0]),
            ];
        }

        // Handle regular name="value" syntax
        preg_match_all('/(?:^|\s)(?!:)([A-Za-z0-9_:-]+)\s*=\s*"([^"]*)"/', $attributesString, $matches, PREG_SET_ORDER);
        foreach ($matches as $m) {
            $attributeName = str($m[1])->camel()->toString();
            if (isset($attributes[$attributeName])) continue;

            $attributes[$attributeName] = [
                'isDynamic' => false,
                'value' => $m[2],
                'original' => trim($m[0]),
            ];
        }

        // Handle boolean attributes (single words without values)
        // First strip quoted values so we don't match words inside them
        $stripped = preg_replace('/"[^"]*"/', '""', $attributesString);
        preg_match_all('/(?:^|\s)([A-Za-z0-9_:-]+)(?=\s|$)/', $stripped, $matches, PREG_SET_ORDER);
        foreach ($matches as $m) {
            $attributeName = str($m[1])->camel()->toString();
            if (isset($attributes[$attributeName])) continue;

            $attributes[$attributeName] = [
                'isDynamic' => false,
                'value' => true,
                'original' => trim($m[0]),
            ];
        }

        return $attributes;
    }

    /**
     * Parse an array of attributes into an attributes string.
     *
     * For example, the array:
     * [
     *     'foo' => [
     *         'isDynamic' => false,
     *         'value' => 'bar',
     *         'original' => 'foo="bar"',
     *     ],
     *     'name' => [
     *         'isDynamic' => true,
     *         'value' => '$name',
     *         'original' => ':name="$name"',
     *     ],
     *     'baz' => [
     *         'isDynamic' => true,
     *         'value' => '$baz',
     *         'original' => ':$baz',
     *     ],
     *     'searchable' => [
     *         'isDynamic' => false,
     *         'value' => true,
     *         'original' => 'searchable',
     *     ],
     * ]
     *
     * will be parsed into the string:
     * `foo="bar" :name="$name" :$baz searchable`
     */
    public function parseAttributesArrayToPropString(array $attributes): string
    {
        $attributesString = '';

        foreach ($attributes as $attributeName => $attributeValue) {
            $attributesString .= $attributeValue['original'] . ' ';
        }

        return trim($attributesString);
    }


    /**
     *
     * Parse an array of attributes into a runtime array string.
     *
     * For example, the array:
     * [
     *     'foo' => [
     *         'isDynamic' => false,
     *         'value' => 'bar',
     *         'original' => 'foo="bar"',
     *     ],
     *     'name' => [
     *         'isDynamic' => true,
     *         'value' => '$name',
     *         'original' => ':name="$name"',
     *     ],
     *     'baz' => [
     *         'isDynamic' => true,
     *         'value' => '$baz',
     *         'original' => ':$baz',
     *     ],
     *     'searchable' => [
     *         'isDynamic' => false,
     *         'value' => true,
     *         'original' => 'searchable',
     *     ],
     * ]
     *
     * will be parsed into the string:
     * `['foo' => 'bar', 'name' => $name, 'baz' => $baz, 'searchable' => true]`
     */
    public function parseAttributesArrayToRuntimeArrayString(array $attributes): string
    {
        $arrayParts = [];

        foreach ($attributes as $attributeName => $attributeData) {
            if ($attributeData['isDynamic']) {
                $arrayParts[] = "'" . addslashes($attributeName) . "' => " . $attributeData['value'];
                continue;
            }

            $value = $attributeData['value'];

            // Handle different value types
            if (is_bool($value)) {
                $valueString = $value ? 'true' : 'false';
            } elseif (is_string($value)) {
                $valueString = "'" . addslashes($value) . "'";
            } elseif (is_null($value)) {
                $valueString = 'null';
            } else {
                $valueString = (string) $value;
            }

            $arrayParts[] = "'" . addslashes($attributeName) . "' => " . $valueString;
        }

        return '[' . implode(', ', $arrayParts) . ']';
    }

    /**
     * Parse PHP array string syntax (typically used in `@aware` or `@props` directives) into a PHP array.
     *
     * For example, the string:
     * `['foo', 'bar' => 'baz']`
     *
     * will be parsed into the array:
     * [
     *     'foo',
     *     'bar' => 'baz',
     * ]
     */
    public function parseArrayStringIntoArray(string $arrayString): array
    {
        // Remove any leading/trailing whitespace and newlines
        $arrayString = trim($arrayString);

        // Remove square brackets if present
        if (str_starts_with($arrayString, '[') && str_ends_with($arrayString, ']')) {
            $arrayString = substr($arrayString, 1, -1);
        }

        // Split by comma, but be careful about commas inside quotes
        $items = [];
        $current = '';
        $inQuotes = false;
        $quoteChar = '';

        for ($i = 0; $i < strlen($arrayString); $i++) {
            $char = $arrayString[$i];

            if (($char === '"' || $char === "'") && !$inQuotes) {
                $inQuotes = true;
                $quoteChar = $char;
                $current .= $char;
            } elseif ($char === $quoteChar && $inQuotes) {
                $inQuotes = false;
                $quoteChar = '';
                $current .= $char;
            } elseif ($char === ',' && !$inQuotes) {
                $items[] = trim($current);
                $current = '';
            } else {
                $current .= $char;
            }
        }

        if (!empty(trim($current))) {
            $items[] = trim($current);
        }

        $array = [];
        foreach ($items as $item) {
            $item = trim($item);
            if (empty($item)) continue;

            // Check if it's a key-value pair (contains =>)
            if (strpos($item, '=>') !== false) {
                $parts = explode('=>', $item, 2);
                $key = trim(trim($parts[0]), "'\"");
                $value = trim(trim($parts[1]), "'\"");

                // Convert null string to actual null
                if ($value === 'null') {
                    $value = null;
                }

                $array[$key] = $value;
            } else {
                // It's just a key
                $key = trim(trim($item), "'\"");
                $array[] = $key;
            }
        }

        return $array;
    }
}
