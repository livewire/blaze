<?php

namespace Livewire\Blaze\Support;

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
        $this->content = file_exists($this->path) ? file_get_contents($this->path) : '';
        $this->directives = new Directives($this);
    }

    public function exists(): bool
    {
        return file_exists($this->path);
    }
}
