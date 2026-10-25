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
     * Version of the generated code. Bump it when a release changes the compiled output,
     * so views and components compiled by an older version are recompiled.
     */
    public const COMPILED_VERSION = 'v4';

    /**
     * Parse a @blaze directive expression into its parameters.
     */
    public static function parseBlazeDirective(string $expression): array
    {
        $params = BlazeDirective::parseParameters($expression);

        if (data_get($params, 'compile', true) === false && data_get($params, 'memo', false) === true) {
            throw new \InvalidArgumentException('Cannot set `memo: true` with `compile: false` in @blaze directive.');
        }

        if (data_get($params, 'compile', true) === false && data_get($params, 'fold', false) === true) {
            throw new \InvalidArgumentException('Cannot set `fold: true` with `compile: false` in @blaze directive.');
        }

        return $params;
    }

    /**
     * Generate a unique hash for a component path.
     */
    public static function hash(string $componentPath): string
    {
        return hash('xxh128', self::COMPILED_VERSION . $componentPath);
    }
}
