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

        if (! file_exists($this->path)) {
            throw new \Exception("Source file for component [{$name}] not found at path [{$this->path}]");
        }

        $this->content = file_get_contents($this->path);
        $this->directives = new Directives($this->content);
    }
}