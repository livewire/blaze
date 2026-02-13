<?php

namespace Livewire\Blaze\Support;

use Illuminate\Support\Arr;
use Livewire\Blaze\BladeService;

/**
 * Parses component attribute strings into structured arrays, handling all Blade syntaxes.
 */
class AttributeParser
{
    /**
     * Parse an attribute string into a keyed array of attribute metadata.
     *
     * Handles ::attr, :attr, :$var, attr="val", and boolean syntaxes.
     */
    public function parseAttributeStringToArray(string $attributesString): array
    {
        $attributesString = BladeService::preprocessAttributeString($attributesString);

        $attributes = [];

        preg_match_all('/(?:^|\s)::([A-Za-z0-9_-]+)\s*=\s*"([^"]*)"/', $attributesString, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        foreach ($matches as $m) {
            $pos = $m[0][1];
            $m = array_column($m, 0);
            $attributeName = str($m[1])->camel()->toString();
            if (isset($attributes[$attributeName])) {
                continue;
            }

            $attributes[$attributeName] = [
                'name' => $m[1],
                'isDynamic' => false,
                'value' => $m[2],
                'original' => trim($m[0]),
                'quotes' => '"',
                'position' => $pos,
            ];
        }

        preg_match_all("/(?:^|\s)::([A-Za-z0-9_-]+)\s*=\s*'([^']*)'/", $attributesString, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        foreach ($matches as $m) {
            $pos = $m[0][1];
            $m = array_column($m, 0);
            $attributeName = str($m[1])->camel()->toString();
            if (isset($attributes[$attributeName])) {
                continue;
            }

            $attributes[$attributeName] = [
                'name' => $m[1],
                'isDynamic' => false,
                'value' => $m[2],
                'original' => trim($m[0]),
                'quotes' => "'",
                'position' => $pos,
            ];
        }

        preg_match_all('/(?:^|\s):(?!:)([A-Za-z0-9_.:-]+)\s*=\s*"([^"]*)"/', $attributesString, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        foreach ($matches as $m) {
            $pos = $m[0][1];
            $m = array_column($m, 0);
            $attributeName = str($m[1])->camel()->toString();
            if (isset($attributes[$attributeName])) {
                continue;
            }

            $attributes[$attributeName] = [
                'name' => $m[1],
                'isDynamic' => true,
                'value' => $m[2],
                'original' => trim($m[0]),
                'quotes' => '"',
                'position' => $pos,
            ];
        }

        preg_match_all("/(?:^|\s):(?!:)([A-Za-z0-9_.:-]+)\s*=\s*'([^']*)'/", $attributesString, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        foreach ($matches as $m) {
            $pos = $m[0][1];
            $m = array_column($m, 0);
            $attributeName = str($m[1])->camel()->toString();
            if (isset($attributes[$attributeName])) {
                continue;
            }

            $attributes[$attributeName] = [
                'name' => $m[1],
                'isDynamic' => true,
                'value' => $m[2],
                'original' => trim($m[0]),
                'quotes' => "'",
                'position' => $pos,
            ];
        }

        preg_match_all('/(?:^|\s):\$([A-Za-z0-9_.:-]+)(?=\s|$)/', $attributesString, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        foreach ($matches as $m) {
            $pos = $m[0][1];
            $m = array_column($m, 0);
            $raw = $m[1];
            $attributeName = str($raw)->camel()->toString();
            if (isset($attributes[$attributeName])) {
                continue;
            }

            $attributes[$attributeName] = [
                'name' => $m[1],
                'isDynamic' => true,
                'value' => '$'.$raw,
                'original' => trim($m[0]),
                'quotes' => '"',
                'position' => $pos,
            ];
        }

        preg_match_all('/(?:^|\s)(?!:)([A-Za-z0-9_.:-]+)\s*=\s*"([^"]*)"/', $attributesString, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        foreach ($matches as $m) {
            $pos = $m[0][1];
            $m = array_column($m, 0);
            $attributeName = str($m[1])->camel()->toString();
            if (isset($attributes[$attributeName])) {
                continue;
            }

            $attributes[$attributeName] = [
                'name' => $m[1],
                'isDynamic' => false,
                'value' => $m[2],
                'original' => trim($m[0]),
                'quotes' => '"',
                'position' => $pos,
            ];
        }

        preg_match_all("/(?:^|\s)(?!:)([A-Za-z0-9_.:-]+)\s*=\s*'([^']*)'/", $attributesString, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        foreach ($matches as $m) {
            $pos = $m[0][1];
            $m = array_column($m, 0);
            $attributeName = str($m[1])->camel()->toString();
            if (isset($attributes[$attributeName])) {
                continue;
            }

            $attributes[$attributeName] = [
                'name' => $m[1],
                'isDynamic' => false,
                'value' => $m[2],
                'original' => trim($m[0]),
                'quotes' => "'",
                'position' => $pos,
            ];
        }

        // Boolean attributes - strip quoted values first to avoid false matches
        $stripped = preg_replace('/"[^"]*"/', '""', $attributesString);
        $stripped = preg_replace("/'[^']*'/", "''", $stripped);
        preg_match_all('/(?:^|\s)([A-Za-z0-9_.:-]+)(?=\s|$)/', $stripped, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        foreach ($matches as $m) {
            $pos = $m[0][1];
            $m = array_column($m, 0);
            $attributeName = str($m[1])->camel()->toString();
            if (isset($attributes[$attributeName])) {
                continue;
            }

            $attributes[$attributeName] = [
                'name' => $m[1],
                'isDynamic' => false,
                'value' => true,
                'original' => trim($m[0]),
                'quotes' => '',
                'position' => $pos,
            ];
        }

        $attributes = Arr::sort($attributes, fn ($a) => $a['position']);
        $attributes = Arr::map($attributes, fn ($a) => tap($a, function (&$a) {
            unset($a['position']);
        }));

        return $attributes;
    }

    /**
     * Reconstruct the original attribute string from parsed attribute data.
     */
    public function parseAttributesArrayToPropString(array $attributes): string
    {
        $attributesString = '';

        foreach ($attributes as $attributeName => $attributeValue) {
            $attributesString .= $attributeValue['original'].' ';
        }

        return trim($attributesString);
    }

    /**
     * Convert parsed attributes into a PHP array string for runtime evaluation.
     */
    public function parseAttributesArrayToRuntimeArrayString(array $attributes): string
    {
        $arrayParts = [];

        foreach ($attributes as $attributeName => $attributeData) {
            if ($attributeData['isDynamic']) {
                $arrayParts[] = "'".addslashes($attributeName)."' => ".$attributeData['value'];

                continue;
            }

            $value = $attributeData['value'];

            if (is_bool($value)) {
                $valueString = $value ? 'true' : 'false';
            } elseif (is_string($value) && str_contains($value, '{{')) {
                // Blade echo syntax (e.g. {{ $order->avatar }}) must be compiled
                // to a PHP expression so the runtime value is used (not the literal template string).
                // This is critical for memoization keys to be unique per evaluated value.
                $valueString = Utils::compileAttributeEchos($value);
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
