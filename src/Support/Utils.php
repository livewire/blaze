<?php

namespace Livewire\Blaze\Support;

use Livewire\Blaze\Directive\BlazeDirective;
use Livewire\Blaze\Parser\Attribute;

/**
 * Static utility helpers used across the Blaze pipeline.
 */
class Utils
{
    /**
     * Parse a @blaze directive expression into its parameters.
     */
    public static function parseBlazeDirective(string $expression): array
    {
        return BlazeDirective::parseParameters($expression);
    }

    /**
     * Generate a unique hash for a component path.
     */
    public static function hash(string $componentPath): string
    {
        return hash('xxh128', 'v2' . $componentPath);
    }
}
