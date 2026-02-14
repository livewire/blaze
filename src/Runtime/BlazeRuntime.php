<?php

namespace Livewire\Blaze\Runtime;

use Illuminate\Contracts\View\Factory;
use Illuminate\Foundation\Application;
use Illuminate\Support\ViewErrorBag;
use Livewire\Blaze\BladeService;
use Livewire\Blaze\Support\Utils;
use Livewire\Blaze\Debugger;
use Illuminate\View\Compilers\Compiler;

/**
 * Runtime context shared with all Blaze-compiled components via $__blaze.
 */
class BlazeRuntime
{
    public readonly Factory $env;
    public readonly Application $app;
    public readonly Debugger $debugger;
    public readonly Compiler $compiler;
    public readonly string $compiledPath;
    protected ViewErrorBag $errors;

    protected array $paths = [];
    protected array $compiled = [];

    protected array $dataStack = [];
    protected array $slotsStack = [];

    public function __construct()
    {
        $this->env = app('view');
        $this->app = app();
        $this->debugger = app('blaze.debugger');
        $this->compiler = app('blade.compiler');
        $this->compiledPath = config('view.compiled');
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
     */
    public function resolve(string $component): string
    {
        if (isset($this->paths[$component])) {
            $path = $this->paths[$component];
        } else {
            $path = $this->paths[$component] = BladeService::componentNameToPath($component);
        }

        $hash = Utils::hash($path);
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

    /**
     * Lazy-load $errors since middleware sets them after BlazeRuntime is constructed.
     */
    public function __get(string $name): mixed
    {
        if ($name === 'errors') {
            return $this->errors ??= $this->env->shared('errors') ?? new ViewErrorBag;
        }

        throw new \InvalidArgumentException("Property {$name} does not exist");
    }
}
