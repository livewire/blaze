<?php

namespace Livewire\Blaze\Exceptions;

use Exception;

/**
 * Thrown when an @aware directive has an invalid expression.
 */
class InvalidAwareDefinitionException extends Exception
{
    public function __construct(string $expression, string $reason = '')
    {
        $message = "Invalid @aware definition: {$expression}";

        if ($reason) {
            $message .= " ({$reason})";
        }

        parent::__construct($message);
    }
}
