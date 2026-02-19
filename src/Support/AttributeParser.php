<?php

namespace Livewire\Blaze\Support;

use Livewire\Blaze\BladeService;
use Livewire\Blaze\Parser\Attribute;

/**
 * Parses component attribute strings into structured arrays, handling all Blade syntaxes.
 */
class AttributeParser
{
    /**
     * Parse an attribute string into a keyed array of Attribute objects.
     *
     * Uses Laravel's preprocessing pipeline to normalize all attribute syntaxes
     * (:$var, :attr, {{ $attributes }}, @class, @style) into a uniform format,
     * then matches with Laravel's single attribute regex.
     *
     * @return array<string, Attribute>
     */
    public function parseAttributeStringToArray(string $attributesString): array
    {
        $attributesString = BladeService::preprocessAttributeString($attributesString);

        preg_match_all(LaravelRegex::ATTRIBUTE_PATTERN, $attributesString, $matches, PREG_SET_ORDER);

        $attributes = [];

        foreach ($matches as $match) {
            $name = $match['attribute'];
            $value = isset($match['value']) ? BladeService::stripQuotes($match['value']) : null;
            $isDynamic = false;
            $prefix = '';

            if (str_starts_with($name, 'bind:')) {
                $name = substr($name, 5);
                $isDynamic = true;
                $prefix = ':';
            }

            if (str_starts_with($name, '::')) {
                $name = substr($name, 1);
                $prefix = '::';
            }

            if (is_null($value)) {
                $value = true;
            }

            $quotes = '';
            if (isset($match['value'])) {
                $raw = $match['value'];
                if (str_starts_with($raw, '"')) {
                    $quotes = '"';
                } elseif (str_starts_with($raw, "'")) {
                    $quotes = "'";
                }
            }

            $camelName = str()->camel($name);

            if (isset($attributes[$camelName])) {
                continue;
            }

            $dynamic = $isDynamic || (is_string($value) && str_contains($value, '{{'));

            $attributes[$camelName] = new Attribute(
                name: $name,
                value: $value,
                propName: $camelName,
                dynamic: $dynamic,
                prefix: $prefix,
                quotes: $quotes,
            );
        }

        return $attributes;
    }

    /**
     * Convert parsed attributes into a PHP array string for runtime evaluation.
     *
     * @param  array<string, Attribute>  $attributes
     */
    public function parseAttributesArrayToRuntimeArrayString(array $attributes): string
    {
        $arrayParts = [];

        foreach ($attributes as $attributeName => $attr) {
            if ($attr->dynamic && is_string($attr->value) && (str_contains($attr->value, '{{') || str_contains($attr->value, '{!!'))) {
                // Blade echo syntax (e.g. {{ $order->avatar }} or {!! $rawHtml !!}) must be compiled
                // to a PHP expression so the runtime value is used (not the literal template string).
                // This is critical for memoization keys to be unique per evaluated value.
                $arrayParts[] = "'".addslashes($attributeName)."' => ".Utils::compileAttributeEchos($attr->value);

                continue;
            }

            if ($attr->dynamic) {
                $arrayParts[] = "'".addslashes($attributeName)."' => ".$attr->value;

                continue;
            }

            $value = $attr->value;

            if (is_bool($value)) {
                $valueString = $value ? 'true' : 'false';
            } elseif (is_string($value)) {
                $valueString = "'".addslashes($value)."'";
            } elseif (is_null($value)) {
                $valueString = 'null';
            } else {
                $valueString = (string) $value;
            }

            $arrayParts[] = "'".addslashes($attributeName)."' => ".$valueString;
        }

        return '['.implode(', ', $arrayParts).']';
    }
}
