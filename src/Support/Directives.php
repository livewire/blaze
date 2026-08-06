<?php

namespace Livewire\Blaze\Support;

use Illuminate\Support\Arr;
use Livewire\Blaze\Compiler\ArrayParser;

/**
 * Extracts and queries Blade directives from component source content.
 */
class Directives
{
    /** @var array<string,\Livewire\Blaze\Parser\Nodes\DirectiveNode> */
    protected array $directives;

    protected array $props;
    protected array $aware;
    protected array $blaze;

    public function __construct(array $nodes)
    {
        $this->directives = Arr::keyBy($nodes, 'name');
    }

    /**
     * Check if a directive exists in the content.
     */
    public function has(string $name): bool
    {
        return isset($this->directives[$name]);
    }

    /**
     * Get the expression of a directive, or null if not found.
     */
    public function get(string $name): ?string
    {
        return isset($this->directives[$name]) ? ($this->directives[$name]?->expression ?? '') : null;
    }

    /**
     * Parse a directive's expression as a PHP array.
     */
    public function array(string $name): array|null
    {
        if ($expression = $this->get($name)) {
            return ArrayParser::parse($expression);
        }

        return null;
    }

    /**
     * Get the variable names declared by @props.
     *
     * @return string[]
     */
    public function props(): array
    {
        if (isset($this->props)) {
            return $this->props;
        }

        if ($definition = $this->array('props')) {
            return $this->props = collect($definition)->map(fn ($value, $key) => is_int($key) ? $value : $key)->values()->all();
        }

        return $this->props = [];
    }

    /**
     * Get the variable names declared by @aware.
     *
     * @return string[]
     */
    public function aware(): array
    {
        if (isset($this->aware)) {
            return $this->aware;
        }

        if ($definition = $this->array('aware')) {
            return $this->aware = collect($definition)->map(fn ($value, $key) => is_int($key) ? $value : $key)->values()->all();
        }

        return $this->aware = [];
    }

    /**
     * Query @blaze directive presence or a specific parameter value.
     */
    public function blaze(?string $param = null): mixed
    {
        if (is_null($param)) {
            return $this->has('blaze');
        }

        if (array_key_exists($param, $this->blaze)) {
            return $this->blaze[$param];
        }

        if ($expression = $this->get('blaze')) {
            return $this->blaze[$param] = Utils::parseBlazeDirective($expression)[$param] ?? null;
        }

        return $this->blaze[$param] = null;
    }
}
