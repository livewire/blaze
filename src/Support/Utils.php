<?php

namespace Livewire\Blaze\Support;

use Livewire\Blaze\BladeService;
use Livewire\Blaze\Compiler\ArrayParser;
use Livewire\Blaze\Directive\BlazeDirective;

class Utils
{
    public static function componentNameToPath(string $name): string
    {
        return (new BladeService)->componentNameToPath($name);
    }
        
    public static function compileAttributeEchos(string $value): string
    {
        return (new BladeService)->compileAttributeEchos($value);
    }

    public static function parseBlazeDirective(string $expression): array
    {
        return BlazeDirective::parseParameters($expression);
    }

    public static function parseAttributeStringToArray(string $attributeString): array
    {
        return (new AttributeParser)->parseAttributeStringToArray($attributeString);
    }

    /**
     * Parse an array expression from a directive (e.g., @props, @aware).
     * 
     * Returns an associative array where keys are prop names and values are defaults.
     */
    public static function parseArrayContent(string $expression): array
    {
        try {
            return (new ArrayParser)->parse($expression);
        } catch (\Exception $e) {
            return [];
        }
    }
}