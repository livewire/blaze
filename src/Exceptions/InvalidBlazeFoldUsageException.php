<?php

namespace Livewire\Blaze\Exceptions;

/**
 * Thrown when a component uses @blaze fold with incompatible patterns (e.g., $errors, @csrf, session()).
 */
class InvalidBlazeFoldUsageException extends \Exception
{
    protected string $componentPath;

    protected string $problematicPattern;

    protected string $mitigation;

    protected function __construct(string $componentPath, string $problematicPattern, string $reason, string $mitigation)
    {
        $this->componentPath = $componentPath;
        $this->problematicPattern = $problematicPattern;
        $this->mitigation = $mitigation;

        $message = "Invalid @blaze fold usage in component '{$componentPath}'."
            . " Detected pattern: {$problematicPattern}."
            . " Reason: {$reason}."
            . " Mitigation: {$mitigation}.";

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

    /**
     * Get the suggested mitigation.
     */
    public function getMitigation(): string
    {
        return $this->mitigation;
    }

    protected static function defaultMitigation(): string
    {
        return "Disable folding for this component (e.g. remove '@blaze(fold: true)' or set fold: false for its path), or wrap the dynamic section in '@unblaze ... @endunblaze'.";
    }

    /** Create exception for @aware usage. */
    public static function forAware(string $componentPath): self
    {
        return new self(
            $componentPath,
            '@aware',
            'Components with @aware should not use @blaze fold as they depend on parent component state',
            self::defaultMitigation(),
        );
    }

    /** Create exception for $errors usage. */
    public static function forErrors(string $componentPath): self
    {
        return new self(
            $componentPath,
            '\\$errors',
            'Components accessing $errors should not use @blaze fold as errors are request-specific',
            self::defaultMitigation(),
        );
    }

    /** Create exception for session() usage. */
    public static function forSession(string $componentPath): self
    {
        return new self(
            $componentPath,
            'session\\(',
            'Components using session() should not use @blaze fold as session data can change',
            self::defaultMitigation(),
        );
    }

    /** Create exception for @error usage. */
    public static function forError(string $componentPath): self
    {
        return new self(
            $componentPath,
            '@error\\(',
            'Components with @error directives should not use @blaze fold as errors are request-specific',
            self::defaultMitigation(),
        );
    }

    /** Create exception for @csrf usage. */
    public static function forCsrf(string $componentPath): self
    {
        return new self(
            $componentPath,
            '@csrf',
            'Components with @csrf should not use @blaze fold as CSRF tokens are request-specific',
            self::defaultMitigation(),
        );
    }

    /** Create exception for auth() usage. */
    public static function forAuth(string $componentPath): self
    {
        return new self(
            $componentPath,
            'auth\\(\\)',
            'Components using auth() should not use @blaze fold as authentication state can change',
            self::defaultMitigation(),
        );
    }

    /** Create exception for request() usage. */
    public static function forRequest(string $componentPath): self
    {
        return new self(
            $componentPath,
            'request\\(\\)',
            'Components using request() should not use @blaze fold as request data varies',
            self::defaultMitigation(),
        );
    }

    /** Create exception for old() usage. */
    public static function forOld(string $componentPath): self
    {
        return new self(
            $componentPath,
            'old\\(',
            'Components using old() should not use @blaze fold as old input is request-specific',
            self::defaultMitigation(),
        );
    }

    /** Create exception for @once usage. */
    public static function forOnce(string $componentPath): self
    {
        return new self(
            $componentPath,
            '@once',
            'Components with @once should not use @blaze fold as @once maintains runtime state',
            self::defaultMitigation(),
        );
    }

}
