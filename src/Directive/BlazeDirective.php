<?php

namespace Livewire\Blaze\Directive;

use Illuminate\Support\Facades\Blade;

class BlazeDirective
{
    public static function registerFallback(): void
    {
        Blade::directive('blaze', fn () => '');
    }

    public static function getParameters(string $source): ?array
    {
        // If there is no @blaze directive, return null
        if (! preg_match('/^\s*(?:\/\*.*?\*\/\s*)*@blaze(?:\s*\(([^)]+)\))?/s', $source, $matches)) {
            return null;
        }

        // If there are no parameters, return an empty array
        if (empty($matches[1])) {
            return [];
        }

        return self::parseParameters($matches[1]);
    }

    /**
     * Parse directive parameters
     *
     * For example, the string:
     * "fold: true, safe: ['name', 'title']"
     *
     * will be parsed into the array:
     * [
     *     'fold' => true,
     *     'safe' => ['name', 'title'],
     * ]
     */
    public static function parseParameters(string $paramString): array
    {
        $params = [];

        // First, handle array parameters (e.g., safe: ['name', 'title'])
        if (preg_match_all('/(\w+)\s*:\s*\[([^\]]*)\]/', $paramString, $arrayMatches, PREG_SET_ORDER)) {
            foreach ($arrayMatches as $match) {
                $key = $match[1];
                $arrayContent = $match[2];
                $params[$key] = self::parseArrayContent($arrayContent);
            }

            // Remove array parameters from string before processing scalar parameters
            $paramString = preg_replace('/(\w+)\s*:\s*\[[^\]]*\]/', '', $paramString);
        }

        // Then handle scalar parameter parsing for key:value pairs
        if (preg_match_all('/(\w+)\s*:\s*(\w+)/', $paramString, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $key = $match[1];
                $value = $match[2];

                // Convert string boolean values
                if (in_array(strtolower($value), ['true', 'false'])) {
                    $params[$key] = strtolower($value) === 'true';
                } else {
                    $params[$key] = $value;
                }
            }
        }

        return $params;
    }

    /**
     * Parse array content from a directive parameter.
     *
     * For example, the string:
     * "'name', 'title'"
     *
     * will be parsed into the array:
     * ['name', 'title']
     */
    protected static function parseArrayContent(string $content): array
    {
        $items = [];

        // Match quoted strings (single or double quotes)
        if (preg_match_all('/[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
            $items = $matches[1];
        }

        return $items;
    }
}
