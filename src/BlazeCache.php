<?php

namespace Livewire\Blaze;

use Illuminate\Support\Facades\Cache;

class BlazeCache
{
    protected static array $rendered = [];
    protected static bool $shouldRender = true;

    public function remember($key, \Closure $callback)
    {
        $cacheKey = $this->getCacheKey($key);
        
        return Cache::remember($cacheKey, config('blaze.cache.ttl', 3600), $callback);
    }

    public function hasRendered($key)
    {
        if (isset(static::$rendered[$key])) {
            return true;
        }

        static::$rendered[$key] = true;
        return false;
    }

    public static function shouldRender()
    {
        return static::$shouldRender;
    }

    public static function setShouldRender(bool $shouldRender)
    {
        static::$shouldRender = $shouldRender;
    }

    protected function getCacheKey($key)
    {
        $prefix = config('blaze.cache.prefix', 'blaze_');
        
        if (is_array($key)) {
            $key = md5(serialize($key));
        }

        return $prefix . $key;
    }

    public function flush()
    {
        static::$rendered = [];
        
        $prefix = config('blaze.cache.prefix', 'blaze_');
        
        // Clear all cached items with the blaze prefix
        // This is a simplified version - in production you'd want more targeted clearing
        Cache::flush();
    }

    public function forget($key)
    {
        $cacheKey = $this->getCacheKey($key);
        Cache::forget($cacheKey);
        
        unset(static::$rendered[$key]);
    }
}