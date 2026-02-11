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

    public string $compiledPath;

    // $errors must be fetched lazily they're created later in request lifecycle
    protected ViewErrorBag $errors;

    protected array $paths = [];

    protected array $compiled = [];
    protected array $dataStack = [];
    protected array $slotsStack = [];
    protected array $counts = [];

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
            $path = $this->paths[$component] = BladeService::componentNameToPath($component);
        }

        $hash = TagCompiler::hash($path);
        $compiled = $this->compiledPath.'/'.$hash.'.php';

        if (! isset($this->compiled[$path])) {
            $this->ensureCompiled($path, $compiled);
        }

        return $hash;
    }

    /**
     * Get merged data from all stack levels for delegate forwarding.
     */
    public function currentComponentData(): array
    {
        return last($this->dataStack);
    }

    /**
     * Get merged slots from all stack levels for delegate forwarding.
     */
    public function mergedComponentSlots(): array
    {
        $result = [];

        for ($i = 0; $i < count($this->slotsStack); $i++) {
            $result = array_merge($result, $this->slotsStack[$i]);
        }

        return $result;
    }

    /**
     * Push component data onto the stack for @aware lookups.
     */
    public function pushData(array $data): void
    {
        if ($attributes = $data['attributes'] ?? null) {
            unset($data['attributes']);

            $this->dataStack[] = $this->normalizeKeys(array_merge($attributes->all(), $data));
            $this->slotsStack[] = [];
        } else {
            $this->dataStack[] = $this->normalizeKeys($data);
            $this->slotsStack[] = [];
        }
    }

    /**
     * Normalize array keys from kebab-case to camelCase.
     */
    protected function normalizeKeys(array $data): array
    {
        $normalized = [];

        foreach ($data as $key => $value) {
            $normalized[\Illuminate\Support\Str::camel($key)] = $value;
        }

        return $normalized;
    }

    /**
     * Push slots onto the current stack level for delegate forwarding.
     */
    public function pushSlots(array $slots): void
    {
        if (count($this->slotsStack) > 0) {
            $this->slotsStack[count($this->slotsStack) - 1] = $slots;
        }
    }

    /**
     * Pop component data and slots from the stack.
     */
    public function popData(): void
    {
        array_pop($this->dataStack);
        array_pop($this->slotsStack);
    }

    /**
     * Get consumable data from parent components for @aware.
     * At each level, checks slots first (they override data), then walks up the stack.
     */
    public function getConsumableData(string $key, mixed $default = null): mixed
    {
        // Walk backward through stack, checking slots then data at each level
        for ($i = count($this->dataStack) - 1; $i >= 0; $i--) {
            if (array_key_exists($key, $this->slotsStack[$i])) {
                return $this->slotsStack[$i][$key];
            }
            if (array_key_exists($key, $this->dataStack[$i])) {
                return $this->dataStack[$i][$key];
            }
        }

        return value($default);
    }

    public function increment(string $name): void
    {
        $this->counts[$name] ??= 0;
        $this->counts[$name]++;
    }
    
    public function getCounts(): array
    {
        return $this->counts;
    }
}
