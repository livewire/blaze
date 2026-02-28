<?php

namespace Livewire\Blaze\Support;

use Illuminate\Support\Str;
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
    public static function parseAttributeStringToArray(string $attributesString): array
    {
        preg_match_all(LaravelRegex::ATTRIBUTE_PATTERN, $attributesString, $matches, PREG_SET_ORDER);

        $attributes = [];

        foreach ($matches as $match) {
            $name = $match['attribute'];
            $value = isset($match['value']) ? static::stripQuotes($match['value']) : null;
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

            $dynamic = $isDynamic || (is_string($value) && (str_contains($value, '{{') || str_contains($value, '{!!')));

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
     * Strip any quotes from the given string.
     * 
     * @see Illuminate\View\Compilers\ComponentTagCompiler::stripQuotes()
     */
    protected static function stripQuotes(string $value)
    {
        return Str::startsWith($value, ['"', '\''])
            ? substr($value, 1, -1)
            : $value;
    }
}
