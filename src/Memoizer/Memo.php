<?php

namespace Livewire\Blaze\Memoizer;

class Memo
{
    protected static $memo = [];

    public static function clear(): void
    {
        self::$memo = [];
    }

    public static function key(string $name, $params = []): string
    {
        ksort($params);

        $params = json_encode($params);

        return 'blaze_memoized_' . $name . ':' . $params;
    }

    public static function has(string $key): bool
    {
        return isset(self::$memo[$key]);
    }

    public static function get(string $key): mixed
    {
        return self::$memo[$key];
    }

    public static function put(string $key, mixed $value): void
    {
        self::$memo[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset(self::$memo[$key]);
    }
}
