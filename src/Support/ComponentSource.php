<?php

namespace Livewire\Blaze\Support;

/**
 * Resolves and caches a component's file path, content, and directive metadata.
 */
class ComponentSource
{
    public readonly string $name;
    public readonly string $path;
    public readonly string $content;
    public readonly Directives $directives;

    public function __construct($name)
    {
        $this->name = $name;
        $this->path = Utils::componentNameToPath($name);
        $this->content = $this->exists() ? file_get_contents($this->path) : '';
        $this->directives = new Directives($this->content);
    }

    /**
     * Check if the component file exists on disk.
     */
    public function exists(): bool
    {
        return file_exists($this->path);
    }
}
