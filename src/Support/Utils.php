<?php

namespace Livewire\Blaze\Support;

use Livewire\Blaze\BladeService;
use Livewire\Blaze\Directive\BlazeDirective;

/**
 * Static utility helpers used across the Blaze pipeline.
 */
class Utils
{
    /**
     * Resolve a component name to its file path.
     */
    public static function componentNameToPath(string $name): string
    {
        return BladeService::componentNameToPath($name);
    }
        
    /**
     * Compile Blade echo syntax within an attribute value.
     */
    public static function compileAttributeEchos(string $value): string
    {
        return BladeService::compileAttributeEchos($value);
    }

    /**
     * Parse a @blaze directive expression into its parameters.
     */
    public static function parseBlazeDirective(string $expression): array
    {
        return BlazeDirective::parseParameters($expression);
    }

    /**
     * Parse an attribute string into a structured array.
     */
    public static function parseAttributeStringToArray(string $attributeString): array
    {
        return (new AttributeParser)->parseAttributeStringToArray($attributeString);
    }

    /**
     * Generate a unique hash for a component path.
     */
    public static function hash(string $componentPath): string
    {
        return hash('xxh128', 'v2' . $componentPath);
    }
}
