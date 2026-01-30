<?php

namespace Livewire\Blaze;

class OptimizeBuilder
{
    protected array $paths = [];

    /**
     * Register a directory for Blaze optimization.
     *
     * @param  string  $path  The directory path to configure
     * @param  bool  $compile  Whether to enable Blaze compilation (false to exclude)
     * @param  bool  $fold  Whether to enable compile-time folding
     * @param  bool  $memo  Whether to enable runtime memoization
     */
    public function in(string $path, bool $compile = true, bool $fold = false, bool $memo = false): self
    {
        $normalizedPath = rtrim($path, '/');

        $this->paths[$normalizedPath] = [
            'compile' => $compile,
            'fold' => $fold,
            'memo' => $memo,
        ];

        return $this;
    }

    /**
     * Get the configuration for a given component path.
     *
     * Returns the most specific (longest) matching path configuration,
     * or null if no configuration matches.
     */
    public function getConfigForPath(string $componentPath): ?array
    {
        $matchingPath = null;
        $matchingConfig = null;

        foreach ($this->paths as $configuredPath => $config) {
            if (str_starts_with($componentPath, $configuredPath)) {
                // Keep the longest (most specific) match
                if ($matchingPath === null || strlen($configuredPath) > strlen($matchingPath)) {
                    $matchingPath = $configuredPath;
                    $matchingConfig = $config;
                }
            }
        }

        return $matchingConfig;
    }

    /**
     * Check if a component path should be compiled with Blaze.
     *
     * Returns true if:
     * - Path matches a configured directory with compile: true
     *
     * Returns false if:
     * - Path matches a configured directory with compile: false
     *
     * Returns null if:
     * - Path doesn't match any configured directory (use fallback behavior)
     */
    public function shouldCompile(string $componentPath): ?bool
    {
        $config = $this->getConfigForPath($componentPath);

        if ($config === null) {
            return null;
        }

        return $config['compile'];
    }

    /**
     * Check if a component path should enable folding by default.
     *
     * Returns the fold setting from path config, or null if no config.
     */
    public function shouldFold(string $componentPath): ?bool
    {
        $config = $this->getConfigForPath($componentPath);

        return $config['fold'] ?? null;
    }

    /**
     * Check if a component path should enable memoization by default.
     *
     * Returns the memo setting from path config, or null if no config.
     */
    public function shouldMemo(string $componentPath): ?bool
    {
        $config = $this->getConfigForPath($componentPath);

        return $config['memo'] ?? null;
    }

    /**
     * Check if any paths have been configured.
     */
    public function hasPaths(): bool
    {
        return ! empty($this->paths);
    }

    /**
     * Get all configured paths (for debugging/testing).
     */
    public function getPaths(): array
    {
        return $this->paths;
    }
}
