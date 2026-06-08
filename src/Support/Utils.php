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
        $params = BlazeDirective::parseParameters($expression);

        if ($params['compile'] === false && $params['memo'] === true) {
            throw new \InvalidArgumentException('Cannot set `memo: true` with `compile: false` in @blaze directive.');
        }

        if ($params['compile'] === false && $params['fold'] === true) {
            throw new \InvalidArgumentException('Cannot set `fold: true` with `compile: false` in @blaze directive.');
        }

        return $params;
    }

    /**
     * Generate a unique hash for a component path.
     */
    public static function hash(string $componentPath): string
    {
        return hash('xxh128', 'v2' . $componentPath);
    }
}
