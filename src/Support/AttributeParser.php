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

        // :name="..." or :name=$var
        $attributesString = preg_replace_callback('/(\s*):([a-zA-Z0-9_-]+)\s*=\s*("[^"]*"|\$[a-zA-Z0-9_]+)/', function ($matches) use (&$attributePlaceholders, &$attributeNameToPlaceholder) {
            $whitespace = $matches[1];
            $attributeName = $matches[2];
            $attributeValue = $matches[3];

            // Strip double quotes from attribute value...
            $attributeValue = trim($attributeValue, '"');

            $placeholder = 'ATTR_PLACEHOLDER_' . count($attributePlaceholders);
            if (preg_match('/^"\$([a-zA-Z0-9_]+)"$/', $attributeValue, $m)) {
                $attributePlaceholders[$placeholder] = '{{ $' . $m[1] . ' }}';
            } elseif ($attributeValue !== '' && $attributeValue[0] === '$') {
                $attributePlaceholders[$placeholder] = '{{ ' . $attributeValue . ' }}';
            } else {
                $attributePlaceholders[$placeholder] = $attributeValue;
            }
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

        // Echoes inside quoted attribute values: foo {{ $bar }}
        $attributesString = preg_replace_callback('/(\s*[a-zA-Z0-9_-]+\s*=\s*")([^\"]*)(\{\{[^}]+\}\})([^\"]*)(")/', function ($matches) use (&$attributePlaceholders) {
            $before = $matches[1] . $matches[2];
            $echo = $matches[3];
            $after = $matches[4] . $matches[5];

            $placeholder = 'ATTR_PLACEHOLDER_' . count($attributePlaceholders);
            $attributePlaceholders[$placeholder] = $echo;

            return $before . $placeholder . $after;
        }, $attributesString);

        return $attributesString;
    }

    public function parseToArray(string $attributesString): array
    {
        $attributes = [];
        $processedPositions = [];

        // Handle :name="..." or :name=$var syntax
        preg_match_all('/(\s*):([a-zA-Z0-9_-]+)\s*=\s*("[^"]*"|\$[a-zA-Z0-9_]+)/', $attributesString, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        foreach ($matches as $match) {
            $start = $match[0][1];
            $end = $start + strlen($match[0][0]);
            $processedPositions[] = [$start, $end];

            $attributeName = str($match[2][0])->camel()->toString();
            $attributeValue = trim($match[3][0], '"');
            $original = trim($match[0][0]);

            $attributes[$attributeName] = [
                'isDynamic' => true,
                'value' => $attributeValue,
                'original' => $original,
            ];
        }

        // Handle short :$var syntax (expands to :var="$var")
        preg_match_all('/(\s*):\$([a-zA-Z0-9_-]+)(?=\s|$)/', $attributesString, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        foreach ($matches as $match) {
            $start = $match[0][1];
            $end = $start + strlen($match[0][0]);

            // Skip if this position was already processed
            $skip = false;
            foreach ($processedPositions as [$procStart, $procEnd]) {
                if ($start >= $procStart && $end <= $procEnd) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) continue;

            $processedPositions[] = [$start, $end];

            $attributeName = str($match[2][0])->camel()->toString();
            $attributeValue = '$' . $match[2][0];
            $original = trim($match[0][0]);

            $attributes[$attributeName] = [
                'isDynamic' => true,
                'value' => $attributeValue,
                'original' => $original,
            ];
        }

        // Handle regular name="value" syntax
        preg_match_all('/(\s*)([a-zA-Z0-9_-]+)\s*=\s*("[^"]*")/', $attributesString, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        foreach ($matches as $match) {
            $start = $match[0][1];
            $end = $start + strlen($match[0][0]);

            // Skip if this position was already processed
            $skip = false;
            foreach ($processedPositions as [$procStart, $procEnd]) {
                if ($start >= $procStart && $end <= $procEnd) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) continue;

            $processedPositions[] = [$start, $end];

            $attributeName = str($match[2][0])->camel()->toString();
            $attributeValue = trim($match[3][0], '"');
            $original = trim($match[0][0]);

            $attributes[$attributeName] = [
                'isDynamic' => false,
                'value' => $attributeValue,
                'original' => $original,
            ];
        }

        // Handle boolean attributes (single words without values)
        preg_match_all('/(\s*)([a-zA-Z0-9_-]+)(?=\s|$)/', $attributesString, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        foreach ($matches as $match) {
            $start = $match[0][1];
            $end = $start + strlen($match[0][0]);

            // Skip if this position was already processed
            $skip = false;
            foreach ($processedPositions as [$procStart, $procEnd]) {
                if ($start >= $procStart && $end <= $procEnd) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) continue;

            $attributeName = str($match[2][0])->camel()->toString();
            $original = trim($match[0][0]);

            // Only add if not already processed as a key-value pair
            if (!array_key_exists($attributeName, $attributes)) {
                $attributes[$attributeName] = [
                    'isDynamic' => false,
                    'value' => true,
                    'original' => $original,
                ];
            }
        }

        return $attributes;
    }

    public function parseToString(array $attributes): string
    {
        $attributesString = '';

        foreach ($attributes as $attributeName => $attributeValue) {
            $attributesString .= $attributeValue['original'] . ' ';
        }

        return trim($attributesString);
    }

    /**
     * Parse PHP array string syntax into a PHP array.
     *
     * Supports formats:
     * - ['variant', 'secondVariant' => null]
     * - ["variant", "secondVariant" => null]
     * - [\n    'variant',\n    'secondVariant' => null\n]
     *
     * @param string $arrayString
     * @return array
     */
    public function parseArrayString(string $arrayString): array
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
