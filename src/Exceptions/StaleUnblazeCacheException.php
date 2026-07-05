<?php

namespace Livewire\Blaze\Exceptions;

/**
 * Thrown when a cached compiled file references an @unblaze token whose
 * replacement content is unknown (e.g. it was cached by an older version
 * of Blaze that only stored replacements in memory).
 */
class StaleUnblazeCacheException extends \Exception
{
    public function __construct(string $token)
    {
        parent::__construct("Unknown @unblaze token [{$token}] found in a cached compiled file.");
    }
}
