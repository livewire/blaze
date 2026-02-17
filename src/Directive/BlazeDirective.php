<?php

namespace Livewire\Blaze\Directive;

use Illuminate\Support\Facades\Blade;
use Livewire\Blaze\Compiler\ArrayParser;

/**
 * Parses @blaze directive expressions and their key:value parameters.
 */
class BlazeDirective
{
    /**
     * Extract parameters from a @blaze directive in source, or null if absent.
     */
    public static function getParameters(string $source): ?array
    {
        if (! preg_match('/^\s*(?:\/\*.*?\*\/\s*)*@blaze(?:\s*\(([^)]+)\))?/s', $source, $matches)) {
            return null;
        }

        if (empty($matches[1])) {
            return [];
        }

        return self::parseParameters($matches[1]);
    }

    /**
     * Parse a parameter string like "fold: true, safe: ['name', 'title']" into an array.
     */
    public static function parseParameters(string $paramString): array
    {
        $params = [];

        // Extract array parameters first (e.g., safe: ['name', 'title'])
        if (preg_match_all('/(\w+)\s*:\s*\[([^\]]*)\]/', $paramString, $arrayMatches, PREG_SET_ORDER)) {
            foreach ($arrayMatches as $match) {
                $key = $match[1];
                $arrayContent = $match[2];
                $params[$key] = ArrayParser::parse("[{$arrayContent}]");
            }

            $paramString = preg_replace('/(\w+)\s*:\s*\[[^\]]*\]/', '', $paramString);
        }

        // Then extract scalar key:value pairs
        if (preg_match_all('/(\w+)\s*:\s*(\w+)/', $paramString, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $key = $match[1];
                $value = $match[2];

                if (in_array(strtolower($value), ['true', 'false'])) {
                    $params[$key] = strtolower($value) === 'true';
                } else {
                    $params[$key] = $value;
                }
            }
        }

        return $params;
    }
}
