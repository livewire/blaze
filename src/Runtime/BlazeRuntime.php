<?php

namespace Livewire\Blaze\Runtime;

use Illuminate\Contracts\View\Factory;
use Illuminate\Foundation\Application;
use Illuminate\Support\ViewErrorBag;

class BlazeRuntime
{
    protected array $compiled = [];

    public readonly Factory $env;
    public readonly Application $app;

    // $errors must be fetched lazily they're created later in request lifecycle
    protected ?ViewErrorBag $errors = null;

    /**
     * Stack of component data for @aware support.
     * Each entry is the $__data array passed to a component.
     */
    protected array $dataStack = [];

    public function __construct()
    {
        $this->env = app('view');
        $this->app = app();
    }

    public function __get(string $name): mixed
    {
        if ($name === 'errors') {
            // Cache errors after first access - middleware sets them after
            // BlazeRuntime is constructed, but they don't change during request
            return $this->errors ??= $this->env->shared('errors') ?? new ViewErrorBag;
        }

        throw new \InvalidArgumentException("Property {$name} does not exist");
    }

    public function ensureCompiled(string $path, string $compiledPath): void
    {
        if (isset($this->compiled[$path])) {
            return;
        }

        $this->compiled[$path] = true;

        // Check if compiled file exists AND is fresh
        if (file_exists($compiledPath)) {
            // Check if source is newer than compiled file
            if (file_exists($path) && filemtime($path) <= filemtime($compiledPath)) {
                return;  // Compiled file is up-to-date
            }
            
            // Source is newer, need to recompile (fall through to compile below)
        }

        // Compile the component using Laravel's BladeCompiler
        $compiler = app('blade.compiler');
        $compiler->compile($path);
        
        // Laravel compiles to its own location, but we need it at our hash location
        // Copy the compiled file to the expected hash location
        $laravelCompiledPath = $compiler->getCompiledPath($path);
        if (file_exists($laravelCompiledPath)) {
            $directory = dirname($compiledPath);
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
            copy($laravelCompiledPath, $compiledPath);
        }
    }

    /**
     * Push component data onto the stack for @aware lookups.
     */
    public function pushData(array $data): void
    {
        $this->dataStack[] = $data;
    }

    /**
     * Pop component data from the stack.
     */
    public function popData(): void
    {
        array_pop($this->dataStack);
    }

    /**
     * Get consumable data from parent components for @aware.
     *
     * Walks backward through the stack (most recent parent first)
     * and returns the first matching key, or the default if not found.
     */
    public function getConsumableData(string $key, mixed $default = null): mixed
    {
        for ($i = count($this->dataStack) - 1; $i >= 0; $i--) {
            if (array_key_exists($key, $this->dataStack[$i])) {
                return $this->dataStack[$i][$key];
            }
        }

        return value($default);
    }
}
