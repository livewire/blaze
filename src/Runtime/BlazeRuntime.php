<?php

namespace Livewire\Blaze\Runtime;

use Illuminate\Contracts\View\Factory;
use Illuminate\Foundation\Application;
use Illuminate\Support\ViewErrorBag;
use Livewire\Blaze\BladeService;
use Livewire\Blaze\Support\Utils;
use Livewire\Blaze\Debugger;
use Illuminate\View\Compilers\Compiler;
use Livewire\Blaze\Support\Directives;

/**
 * Runtime context shared with all Blaze-compiled components via $__blaze.
 */
class BlazeRuntime
{
    public readonly Factory $env;
    public readonly Application $app;
    public readonly Debugger $debugger;
    public readonly Compiler $compiler;
    protected ViewErrorBag $errors;

    // Lazily cached from config('view.compiled') on first access via __get.
    // This ensures parallel-testing per-worker path overrides are respected.
    protected ?string $compiledPath = null;

    protected array $paths = [];
    protected array $compiled = [];
    protected array $blazed = [];

    protected array $dataStack = [];
    protected array $slotsStack = [];

    public function __construct()
    {
        $this->env = app('view');
        $this->app = app();
        $this->debugger = app('blaze.debugger');
        $this->compiler = app('blade.compiler');
    }

    /**
     * Compile a component if its source is newer than the cached output.
     */
    public function ensureCompiled(string $path, string $compiledPath): void
    {
        if (isset($this->compiled[$path])) {
            return;
        }

        $this->compiled[$path] = true;

        if (file_exists($compiledPath) && filemtime($path) <= filemtime($compiledPath)) {
            return;
        }

        $this->compiler->compile($path);
    }

    /**
     * Resolve a component name to its compiled hash, compiling if needed.
     *
     * Returns false when the component exists but is not Blaze-eligible
     * (no @blaze directive and not configured for compilation), so the
     * caller can fall back to standard Blade rendering.
     */
    public function resolve(string $component): string|false
    {
        if (isset($this->paths[$component])) {
            $path = $this->paths[$component];
        } else {
            $path = $this->paths[$component] = BladeService::componentNameToPath($component);
        }

        if (! $this->isBlazeComponent($path)) {
            return false;
        }

        $hash = Utils::hash($path);
        $compiled = $this->getCompiledPath().'/'.$hash.'.php';

        if (! isset($this->compiled[$path])) {
            $this->ensureCompiled($path, $compiled);
        }

        return $hash;
    }

    /**
     * Check if a component file is a Blaze component.
     */
    protected function isBlazeComponent(string $path): bool
    {
        if (isset($this->blazed[$path])) {
            return $this->blazed[$path];
        }

        if (! file_exists($path)) {
            return $this->blazed[$path] = false;
        }

        $directives = new Directives(file_get_contents($path));

        if ($directives->blaze()) {
            return $this->blazed[$path] = true;
        }

        $config = app('blaze.config');

        return $this->blazed[$path] = $config->shouldCompile($path)
            || $config->shouldMemoize($path)
            || $config->shouldFold($path);
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
     * Walk the data stack to find a value for @aware, checking slots before data at each level.
     */
    public function getConsumableData(string $key, mixed $default = null): mixed
    {
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

    private function getCompiledPath(): string
    {
        return $this->compiledPath ??= config('view.compiled');
    }

    /**
     * Lazy-load properties whose canonical values are set after BlazeRuntime is constructed
     * ($errors by middleware, compiledPath by parallel testing infrastructure).
     */
    public function __get(string $name): mixed
    {
        return match ($name) {
            'errors' => $this->errors ??= $this->env->shared('errors') ?? new ViewErrorBag,
            'compiledPath' => $this->getCompiledPath(),
            default => throw new \InvalidArgumentException("Property {$name} does not exist"),
        };
    }
}
