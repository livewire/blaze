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
     * "fold: false"
     *
     * will be parsed into the array:
     * [
     *     'fold' => false,
     * ]
     */
    protected static function parseParameters(string $paramString): array
    {
        $params = [];

        // Simple parameter parsing for key:value pairs
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
}
