<?php

namespace Livewire\Blaze\Exceptions;

use Exception;

class InvalidPropsDefinitionException extends Exception
{
    public function __construct(string $expression, string $reason = '')
    {
        $message = "Invalid @props definition: {$expression}";

        if ($reason) {
            $message .= " ({$reason})";
        }

        parent::__construct($message);
    }
}
