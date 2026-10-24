<?php

namespace Livewire\Blaze\Runtime;

use Illuminate\Contracts\View\Factory;
use Illuminate\Foundation\Application;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Compilers\Compiler;
use Livewire\Blaze\BladeService;
use Livewire\Blaze\FrontMatter;
use Livewire\Blaze\Support\Directives;
use Livewire\Blaze\Support\Utils;
use Livewire\Blaze\Debugger\Debugger;

/**
 * Runtime context shared with all Blaze-compiled components via $__blaze.
 */
class BlazeRuntime
{
    public bool $folding = false;

    // Lazily cached from config('view.compiled') on first access via __get.
    // This ensures parallel-testing per-worker path overrides are respected.
    protected ?string $compiledPath = null;

    protected array $paths = [];
    protected array $blazed = [];

    /**
     * @deprecated Kept for compatibility with previously compiled views. No longer used by the compiler.
     */
    protected array $required = [];

    protected array $dataStack = [];
    protected array $slotsStack = [];

    public function __construct(
        public Factory $env,
        public Application $app,
        public Debugger $debugger,
        protected BladeCompiler $compiler,
        protected BladeService $blade,
    ) {
    }

    /**
     * Compile a component if needed and return its compiled path.
     *
     * @param  string|null  $compiledPath  @deprecated Kept for compatibility with previously compiled views. No longer used.
     */
    public function compile(string $path, ?string $compiledPath = null): string
    {
        $compiled = $this->compiler->getCompiledPath($path);

        if (! file_exists($compiled) || filemtime($path) >= filemtime($compiled) || ! (new FrontMatter)->isCurrentVersion($compiled)) {
            $this->compiler->compile($path);
        }

        return $compiled;
    }

    /**
     * Compile a component if its source is newer than the cached output.
     *
     * @deprecated Kept for compatibility with previously compiled views. No longer emitted by the compiler.
     */
    public function ensureRequired(string $path, string $compiledPath): void
    {
        if (isset($this->required[$compiledPath])) {
            return;
        }

        if (function_exists(($this->folding ? '__' : '_') . basename($compiledPath, '.php'))) {
            $this->required[$compiledPath] = true;

            return;
        }

        require $this->compile($path);

        $this->required[$compiledPath] = true;
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
            $path = $this->paths[$component] = $this->blade->componentNameToPath($component);
        }

        if (! $this->isBlazeComponent($path)) {
            return false;
        }

        $hash = Utils::hash($path);

        if (! function_exists(($this->folding ? '__' : '_') . $hash)) {
            require $this->compile($path);
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
    * Revert escaping of data passed through attributes.
    *
    * @deprecated Kept for compatibility with previously compiled views. No longer used by the compiler.
    */
    public function unescapeAttributes($data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            $result[$key] = is_string($value) ? htmlspecialchars_decode($value, ENT_QUOTES) : $value;
        }

        return $result;
    }

    /**
     * Prepare attributes forwarded through a delegate component, like a regular component call.
     *
     * Bound attributes of the delegating component that are still unchanged in its attribute bag
     * are forwarded with their original value and returned as bound, so props get the value Blade
     * would pass and the attribute bag escapes it again. Everything else (static values, echoes
     * escaped by the caller, attributes the component changed) is forwarded as it is in the bag.
     * Without the delegating component's data (outside a Blaze component, or in views compiled by
     * an older version), all values are forwarded as they are. Keys are camel cased with a map back
     * to the original names, so kebab-case props are read from the data.
     *
     * @param  array{0: array, 1: array}|null  $origin  Data and bound keys the delegating component was called with.
     * @return array{0: array, 1: array, 2: array} Data, bound keys and original keys.
     */
    public function delegatedAttributes(array $attributes, ?array $origin = null): array
    {
        $data = $bound = $keys = [];

        [$originData, $originBound] = $origin ?? [[], []];

        $originBound = array_flip($originBound);

        foreach ($attributes as $key => $value) {
            $name = is_string($key) ? Str::camel($key) : $key;

            if ($name !== $key) {
                if (array_key_exists($name, $attributes) || isset($keys[$name])) {
                    $name = $key;
                } else {
                    $keys[$name] = $key;
                }
            }

            if (isset($originBound[$name]) && array_key_exists($name, $originData) && BladeCompiler::sanitizeComponentAttribute($originData[$name]) === $value) {
                $value = $originData[$name];
                $bound[] = $name;
            }

            $data[$name] = $value;
        }

        return [$data, $bound, $keys];
    }

    /**
     * Push component data onto the stack for @aware lookups.
     */
    public function pushData(array $data): void
    {
        if ($attributes = $data['attributes'] ?? null) {
            unset($data['attributes']);

            $data = array_merge($attributes->all(), $data);
        }

        foreach ($data as $key => $value) {
            if (str_contains($key, '-')) {
                $data = $this->normalizeKeys($data);

                break;
            }
        }

        $this->dataStack[] = $data;
        $this->slotsStack[] = [];
    }

    /**
     * Normalize array keys from kebab-case to camelCase.
     */
    protected function normalizeKeys(array $data): array
    {
        $normalized = [];

        foreach ($data as $key => $value) {
            $normalized[Str::camel($key)] = $value;
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
     * Process uncompiled unblaze tags passed through slots or components to handle whitespace.
     */
    public function processPassthroughContent(string $method, string $content): string
    {
        if (! in_array($method, ['ltrim', 'rtrim', 'trim'])) {
            return $content;
        }

        $pattern = '\[STARTCOMPILEDUNBLAZE:([0-9a-zA-Z]+)\].*?\[ENDCOMPILEDUNBLAZE:\1\]';

        // Starts and ends with unblaze, adds :trim
        $content = preg_replace_callback(
            '/^\s*'. $pattern .'\s*$/',
            fn ($m) => str_replace('COMPILEDUNBLAZE:'.$m[1], 'COMPILEDUNBLAZE:'.$m[1].':'.$method, $m[0]),
            $content,
        );

        // Starts with unblaze, adds :ltrim
        $content = preg_replace_callback(
            '/^\s*'. $pattern .'/',
            fn ($m) => $method !== 'rtrim' ? str_replace('COMPILEDUNBLAZE:'.$m[1], 'COMPILEDUNBLAZE:'.$m[1].':ltrim', $m[0]) : $m[0],
            $content,
        );

        // Ends with unblaze, adds :rtrim
        $content = preg_replace_callback(
            '/'. $pattern .'\s*$/',
            fn ($m) => $method !== 'ltrim' ? str_replace('COMPILEDUNBLAZE:'.$m[1], 'COMPILEDUNBLAZE:'.$m[1].':rtrim', $m[0]) : $m[0],
            $content,
        );
        
        return $content;
    }

    private function getCompiledPath(): string
    {
        return $this->compiledPath ??= config('view.compiled');
    }

    /**
     * Set the application instance (used by Octane to swap in the sandbox).
     */
    public function setApplication(Application $app): void
    {
        $this->app = $app;
    }

    /**
     * Clear the component data and slots stacks.
     */
    public function flushState(): void
    {
        $this->dataStack = [];
        $this->slotsStack = [];
    }

    /**
     * Lazy-load properties whose canonical values are set after BlazeRuntime is constructed
     * ($errors by middleware, compiledPath by parallel testing infrastructure).
     */
    public function __get(string $name): mixed
    {
        return match ($name) {
            'errors' => $this->env->getShared()['errors'] ?? new ViewErrorBag,
            'compiledPath' => $this->getCompiledPath(),
            default => throw new \InvalidArgumentException("Property {$name} does not exist"),
        };
    }
}
