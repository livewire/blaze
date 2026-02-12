<?php

namespace Livewire\Blaze;

class Config
{
    protected $fold = [];
    protected $memo = [];
    protected $compile = [];

    /**
     * Alias for add(), used as Blaze::optimize()->in(...).
     */
    public function in(string $path, bool $compile = true, bool $memo = false, bool $fold = false): self
    {
        return $this->add($path, $compile, $memo, $fold);
    }

    public function add(string $path, ?bool $compile = true, ?bool $memo = false, ?bool $fold = false): self
    {
        $this->compile[$path] = $compile;
        $this->memo[$path] = $memo;
        $this->fold[$path] = $fold;

        return $this;
    }

    public function shouldCompile(string $file): bool
    {
        return $this->isEnabled($file, $this->compile);
    }

    public function shouldMemoize(string $file): bool
    {
        return $this->isEnabled($file, $this->memo);
    }

    public function shouldFold(string $file): bool
    {
        return $this->isEnabled($file, $this->fold);
    }

    /**
     * Check if the file is in the configured paths and return the value of the most specific path.
     */
    protected function isEnabled(string $file, array $config): bool
    {
        $file = realpath($file);

        if ($file === false) {
            return false;
        }

        $match = null;
        $paths = array_keys($config);
        $separator = DIRECTORY_SEPARATOR;

        foreach ($paths as $path) {
            $dir = realpath($path);
            $dir = $dir ? rtrim($dir, $separator) . $separator : false;

            if (! $dir || ! str_starts_with($file, $dir)) {
                continue;
            }

            if (! $match || substr_count($dir, $separator) >= substr_count($match, $separator)) {
                $match = $path;
            }
        }

        return $config[$match] ?? false;
    }

    /**
     * Clear all path configuration (primarily for testing).
     */
    public function clear(): self
    {
        $this->compile = [];
        $this->memo = [];
        $this->fold = [];

        return $this;
    }
}