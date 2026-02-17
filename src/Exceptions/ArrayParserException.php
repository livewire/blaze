<?php

namespace Livewire\Blaze\Exceptions;

use Exception;

/**
 * Thrown when a PHP array expression cannot be parsed.
 */
class ArrayParserException extends Exception
{
    public function __construct(
        public readonly string $expression,
        string $reason
    ) {
        parent::__construct($reason);
    }
}
