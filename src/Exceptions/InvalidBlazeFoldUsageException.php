<?php

namespace Livewire\Blaze\Exceptions;

/**
 * Thrown when a component uses @blaze fold with incompatible patterns (e.g., $errors, @csrf, session()).
 */
class InvalidBlazeFoldUsageException extends \Exception
{
    protected string $componentPath;

    protected string $problematicPattern;

    protected function __construct(string $componentPath, string $problematicPattern, string $reason)
    {
        $this->componentPath = $componentPath;
        $this->problematicPattern = $problematicPattern;

        $message = "Invalid @blaze fold usage in component '{$componentPath}': {$reason}";

        parent::__construct($message);
    }

    /**
     * Get the path of the component that triggered the exception.
     */
    public function getComponentPath(): string
    {
        return $this->componentPath;
    }

    /**
     * Get the pattern that caused the exception.
     */
    public function getProblematicPattern(): string
    {
        return $this->problematicPattern;
    }

    /** Create exception for @aware usage. */
    public static function forAware(string $componentPath): self
    {
        return new self(
            $componentPath,
            '@aware',
            'Components with @aware should not use @blaze fold as they depend on parent component state'
        );
    }

    /** Create exception for $errors usage. */
    public static function forErrors(string $componentPath): self
    {
        return new self(
            $componentPath,
            '\\$errors',
            'Components accessing $errors should not use @blaze fold as errors are request-specific'
        );
    }

    /** Create exception for session() usage. */
    public static function forSession(string $componentPath): self
    {
        return new self(
            $componentPath,
            'session\\(',
            'Components using session() should not use @blaze fold as session data can change'
        );
    }

    /** Create exception for @error usage. */
    public static function forError(string $componentPath): self
    {
        return new self(
            $componentPath,
            '@error\\(',
            'Components with @error directives should not use @blaze fold as errors are request-specific'
        );
    }

    /** Create exception for @csrf usage. */
    public static function forCsrf(string $componentPath): self
    {
        return new self(
            $componentPath,
            '@csrf',
            'Components with @csrf should not use @blaze fold as CSRF tokens are request-specific'
        );
    }

    /** Create exception for auth() usage. */
    public static function forAuth(string $componentPath): self
    {
        return new self(
            $componentPath,
            'auth\\(\\)',
            'Components using auth() should not use @blaze fold as authentication state can change'
        );
    }

    /** Create exception for request() usage. */
    public static function forRequest(string $componentPath): self
    {
        return new self(
            $componentPath,
            'request\\(\\)',
            'Components using request() should not use @blaze fold as request data varies'
        );
    }

    /** Create exception for old() usage. */
    public static function forOld(string $componentPath): self
    {
        return new self(
            $componentPath,
            'old\\(',
            'Components using old() should not use @blaze fold as old input is request-specific'
        );
    }

    /** Create exception for @once usage. */
    public static function forOnce(string $componentPath): self
    {
        return new self(
            $componentPath,
            '@once',
            'Components with @once should not use @blaze fold as @once maintains runtime state'
        );
    }

}
