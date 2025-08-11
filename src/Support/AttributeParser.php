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
}