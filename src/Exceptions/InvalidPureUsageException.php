<?php

namespace Livewire\Blaze\Exceptions;

class InvalidPureUsageException extends \Exception
{
    protected string $componentPath;

    protected string $problematicPattern;

    protected function __construct(string $componentPath, string $problematicPattern, string $reason)
    {
        $this->componentPath = $componentPath;
        $this->problematicPattern = $problematicPattern;

        $message = "Invalid @pure usage in component '{$componentPath}': {$reason}";

        parent::__construct($message);
    }

    public function getComponentPath(): string
    {
        return $this->componentPath;
    }

    public function getProblematicPattern(): string
    {
        return $this->problematicPattern;
    }

    public static function forAware(string $componentPath): self
    {
        return new self(
            $componentPath,
            '@aware',
            'Components with @aware should not use @pure as they depend on parent component state'
        );
    }

    public static function forErrors(string $componentPath): self
    {
        return new self(
            $componentPath,
            '\\$errors',
            'Components accessing $errors should not use @pure as errors are request-specific'
        );
    }

    public static function forSession(string $componentPath): self
    {
        return new self(
            $componentPath,
            'session\\(',
            'Components using session() should not use @pure as session data can change'
        );
    }

    public static function forError(string $componentPath): self
    {
        return new self(
            $componentPath,
            '@error\\(',
            'Components with @error directives should not use @pure as errors are request-specific'
        );
    }

    public static function forCsrf(string $componentPath): self
    {
        return new self(
            $componentPath,
            '@csrf',
            'Components with @csrf should not use @pure as CSRF tokens are request-specific'
        );
    }

    public static function forAuth(string $componentPath): self
    {
        return new self(
            $componentPath,
            'auth\\(\\)',
            'Components using auth() should not use @pure as authentication state can change'
        );
    }

    public static function forRequest(string $componentPath): self
    {
        return new self(
            $componentPath,
            'request\\(\\)',
            'Components using request() should not use @pure as request data varies'
        );
    }

    public static function forOld(string $componentPath): self
    {
        return new self(
            $componentPath,
            'old\\(',
            'Components using old() should not use @pure as old input is request-specific'
        );
    }

    public static function forOnce(string $componentPath): self
    {
        return new self(
            $componentPath,
            '@once',
            'Components with @once should not use @pure as @once maintains runtime state'
        );
    }

}