<?php

namespace Livewire\Blaze\Support;

/**
 * Resolves and caches a component's file path and directive metadata.
 */
class ComponentSource
{
    /** @var array<string, static> */
    protected static array $cache = [];

    public readonly string $path;
    public readonly Directives $directives;

    public function __construct(string $path)
    {
        $this->path = $path;
        $this->directives = new Directives($this->exists() ? $this->content() : '');
    }

    /**
     * Get a cached instance for a given path, or create one.
     */
    public static function for(string $path): static
    {
        return static::$cache[$path] ??= new static($path);
    }
    
    /**
     * Check if the component file exists on disk.
     */
    public function exists(): bool
    {
        return file_exists($this->path);
    }

    /**
     * Get the raw source content of the component file.
     */
    public function content(): string
    {
        return file_get_contents($this->path);
    }
}
