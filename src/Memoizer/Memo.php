<?php

namespace Livewire\Blaze\Memoizer;

/**
 * Simple in-memory key-value store for memoized component output.
 */
class Memo
{
    protected static $memo = [];

    /**
     * Clear all memoized entries.
     */
    public static function clear(): void
    {
        self::$memo = [];
    }

    /**
     * Generate a cache key from a component name and its parameters.
     */
    public static function key(string $name, $params = []): string
    {
        ksort($params);

        $params = json_encode($params);

        return 'blaze_memoized_' . $name . ':' . $params;
    }

    /**
     * Check if a key exists in the memo store.
     */
    public static function has(string $key): bool
    {
        return isset(self::$memo[$key]);
    }

    /**
     * Retrieve a value from the memo store.
     */
    public static function get(string $key): mixed
    {
        return self::$memo[$key];
    }

    /**
     * Store a value in the memo store.
     */
    public static function put(string $key, mixed $value): void
    {
        self::$memo[$key] = $value;
    }

    /**
     * Remove a value from the memo store.
     */
    public static function forget(string $key): void
    {
        unset(self::$memo[$key]);
    }
}
