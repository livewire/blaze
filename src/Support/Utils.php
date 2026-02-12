<?php

namespace Livewire\Blaze\Support;

use Livewire\Blaze\BladeService;
use Livewire\Blaze\Directive\BlazeDirective;

class Utils
{
    public static function componentNameToPath(string $name): string
    {
        return BladeService::componentNameToPath($name);
    }
        
    public static function compileAttributeEchos(string $value): string
    {
        return BladeService::compileAttributeEchos($value);
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
     * Generate a unique hash for a component path.
     */
    public static function hash(string $componentPath): string
    {
        return hash('xxh128', 'v2' . $componentPath);
    }
}
