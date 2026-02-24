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
     * Cache of resolved config paths.
     *
     * @var array<string, array{path: string, isFile: bool, depth: int, directory: string|null}>
     */
    protected $resolvedPathCache = [];

    /**
     * Cache of per-file enablement decisions by strategy.
     *
     * @var array{compile: array<string, bool>, memo: array<string, bool>, fold: array<string, bool>}
     */
    protected $enabledCache = [
        'compile' => [],
        'memo' => [],
        'fold' => [],
    ];

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

        // Path rules changed, so prior per-file decisions are stale.
        unset($this->resolvedPathCache[$path]);
        $this->flushEnabledCache();

        return $this;
    }

    /**
     * Check if a file should be compiled based on path configuration.
     */
    public function shouldCompile(string $file): bool
    {
        return $this->isEnabled($file, $this->compile, 'compile');
    }

    /**
     * Check if a file should be memoized based on path configuration.
     */
    public function shouldMemoize(string $file): bool
    {
        return $this->isEnabled($file, $this->memo, 'memo');
    }

    /**
     * Check if a file should be folded based on path configuration.
     */
    public function shouldFold(string $file): bool
    {
        return $this->isEnabled($file, $this->fold, 'fold');
    }

    /**
     * Resolve the most specific matching path and return its configured value.
     */
    protected function isEnabled(string $file, array $config, string $strategy): bool
    {
        $file = realpath($file);

        if ($file === false) {
            return false;
        }

        if (array_key_exists($file, $this->enabledCache[$strategy])) {
            return $this->enabledCache[$strategy][$file];
        }

        $match = null;
        $matchDepth = -1;

        foreach (array_keys($config) as $path) {
            $resolved = $this->resolveConfigPath($path);

            if ($resolved === null) {
                continue;
            }

            // Support exact file matches...
            if ($resolved['isFile']) {
                if ($file !== $resolved['path']) {
                    continue;
                }

                // File matches are the most specific, so they always win...
                $match = $path;

                break;
            }

            if (! str_starts_with($file, $resolved['directory'])) {
                continue;
            }

            if ($resolved['depth'] >= $matchDepth) {
                $match = $path;
                $matchDepth = $resolved['depth'];
            }
        }

        return $this->enabledCache[$strategy][$file] = ($config[$match] ?? false);
    }

    /**
     * Resolve and normalize a configured path.
     *
     * @return array{path: string, isFile: bool, depth: int, directory: string|null}|null
     */
    protected function resolveConfigPath(string $path): ?array
    {
        if (isset($this->resolvedPathCache[$path])) {
            $cached = $this->resolvedPathCache[$path];

            // Re-resolve if the path no longer exists on disk.
            if (file_exists($cached['path'])) {
                return $cached;
            }

            unset($this->resolvedPathCache[$path]);
        }

        $resolved = realpath($path);

        if ($resolved === false) {
            // Do not cache unresolved paths so newly-created directories/files
            // can be picked up by subsequent checks.
            return null;
        }

        $separator = DIRECTORY_SEPARATOR;
        $isFile = is_file($resolved);
        $directory = $isFile ? null : rtrim($resolved, $separator) . $separator;
        $depth = substr_count($directory ?? $resolved, $separator);

        return $this->resolvedPathCache[$path] = [
            'path' => $resolved,
            'isFile' => $isFile,
            'depth' => $depth,
            'directory' => $directory,
        ];
    }

    /**
     * Clear all cached per-file strategy decisions.
     */
    protected function flushEnabledCache(): void
    {
        $this->enabledCache = [
            'compile' => [],
            'memo' => [],
            'fold' => [],
        ];
    }

    /**
     * Clear all path configuration (primarily for testing).
     */
    public function clear(): self
    {
        $this->compile = [];
        $this->memo = [];
        $this->fold = [];
        $this->resolvedPathCache = [];
        $this->flushEnabledCache();

        return $this;
    }
}
