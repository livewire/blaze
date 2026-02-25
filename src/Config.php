<?php

namespace Livewire\Blaze;

/**
 * Manages path-based optimization settings for compile, memo, and fold strategies.
 */
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

    /**
     * Register optimization settings for a given path.
     */
    public function add(string $path, ?bool $compile = true, ?bool $memo = false, ?bool $fold = false): self
    {
        $this->compile[$path] = $compile;
        $this->memo[$path] = $memo;
        $this->fold[$path] = $fold;

        return $this;
    }

    /**
     * Check if a file should be compiled based on path configuration.
     */
    public function shouldCompile(string $file): bool
    {
        return $this->isEnabled($file, $this->compile);
    }

    /**
     * Check if a file should be memoized based on path configuration.
     */
    public function shouldMemoize(string $file): bool
    {
        return $this->isEnabled($file, $this->memo);
    }

    /**
     * Check if a file should be folded based on path configuration.
     */
    public function shouldFold(string $file): bool
    {
        return $this->isEnabled($file, $this->fold);
    }

    /**
     * Resolve the most specific matching path and return its configured value.
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
            $resolved = realpath($path);

            if ($resolved === false) {
                continue;
            }

            // Support exact file matches...
            if (is_file($resolved)) {
                if ($file !== $resolved) {
                    continue;
                }

                // File matches are the most specific, so they always win...
                $match = $path;

                break;
            }

            $dir = rtrim($resolved, $separator) . $separator;

            if (! str_starts_with($file, $dir)) {
                continue;
            }

            if (! $match || substr_count($dir, $separator) >= substr_count($match, $separator)) {
                $match = $path;
            }
        }

        return $match !== null ? ($config[$match] ?? false) : false;
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
