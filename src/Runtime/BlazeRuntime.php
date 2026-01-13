<?php

namespace Livewire\Blaze\Runtime;

use Illuminate\Contracts\View\Factory;
use Illuminate\Foundation\Application;
use Illuminate\Support\ViewErrorBag;
use Livewire\Blaze\BladeService;
use Livewire\Blaze\Compiler\TagCompiler;

class BlazeRuntime
{
    public readonly Factory $env;
    public readonly Application $app;

    // $errors must be fetched lazily they're created later in request lifecycle
    protected ViewErrorBag $errors;

    protected string $compiledPath;
    
    protected array $paths = [];
    protected array $compiled = [];

    /**
     * Stack of component data for @aware support.
     * Each entry is the $__data array passed to a component.
     */
    protected array $dataStack = [];

    public function __construct()
    {
        $this->env = app('view');
        $this->app = app();
        $this->compiledPath = config('view.compiled');
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

        if (file_exists($compiledPath) && filemtime($path) <= filemtime($compiledPath)) {
            return;
        }

        app('blade.compiler')->compile($path);
    }

    public function resolve(string $component): string
    {
        if (isset($this->paths[$component])) {
            $path = $this->paths[$component];
        } else {
            $path = $this->paths[$component] = (new BladeService)->componentNameToPath($component);
        }

        $hash = TagCompiler::hash($path);
        $compiled = $this->compiledPath . '/' . $hash . '.php';
        
        if (! isset($this->compiled[$path])) {
            $this->ensureCompiled($path, $compiled);
        }

        return $hash;
    }

    public function currentComponentData(): array
    {
        return array_reduce($this->dataStack, function ($merged, $data) {
            return array_merge($merged, $data);
        }, []);
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
