<?php

namespace Livewire\Blaze;

class BlazeConfig
{
    protected $fold = [];
    protected $memo = [];
    protected $compile = [];

    public function in(
        string $path,
        ?bool $compile = true,
        ?bool $memo = false,
        ?bool $fold = false,
    ): self
    {
        $this->compile[$path] = $compile;
        $this->memo[$path] = $memo;
        $this->fold[$path] = $fold;

        return $this;
    }

    public function shouldCompile(string $path): bool
    {
        return $this->isInPaths($this->compile, $path);
    }

    public function shouldMemo(string $path): bool
    {
        return $this->isInPaths($this->memo, $path);
    }

    public function shouldFold(string $path): bool
    {
        return $this->isInPaths($this->fold, $path);
    }

    public function isInPaths(array $paths, string $path): bool
    {
        // TODO: This also needs to handle the case where a folder has eg. fold: true
        // and a subfolder has fold: false, in which case the subfolder should override the folder.
        foreach ($paths as $path) {
            // TODO: Verify this is sufficient for checking file paths...
            if (str_starts_with($path, $path)) {
                return true;
            }
        }

        return false;
    }
}