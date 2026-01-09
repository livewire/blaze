<?php

namespace Livewire\Blaze\Exceptions;

use Exception;

class ArrayParserException extends Exception
{
    public function __construct(
        public readonly string $expression,
        string $reason
    ) {
        parent::__construct($reason);
    }
}
