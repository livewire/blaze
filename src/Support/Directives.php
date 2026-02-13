<?php

namespace Livewire\Blaze\Support;

use Illuminate\Support\Arr;
use Livewire\Blaze\BladeService;
use Livewire\Blaze\Compiler\ArrayParser;

/**
 * Extracts and queries Blade directives from component source content.
 */
class Directives
{
    public function __construct(
        protected string $content,
    ) {
    }

    /**
     * Check if a directive exists in the content.
     */
    public function has(string $name): bool
    {
        $result = false;
        
        BladeService::compileDirective($this->content, $name, function () use (&$result) {
            $result = true;
        });
        
        return $result;
    }

    /**
     * Get the expression of a directive, or null if not found.
     */
    public function get(string $name): ?string
    {
        $result = null;

        BladeService::compileDirective($this->content, $name, function ($expression) use (&$result) {
            $result = $expression;
        });
        
        return $result;
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
        if ($definition = $this->array('props')) {
            return collect($definition)->map(fn ($value, $key) => is_int($key) ? $value : $key)->values()->all();
        }

        return [];
    }

    /**
     * Query @blaze directive presence or a specific parameter value.
     */
    public function blaze(?string $param = null): mixed
    {
        if (is_null($param)) {
            return $this->has('blaze');
        }

        if ($expression = $this->get('blaze')) {
            return Utils::parseBlazeDirective($expression)[$param] ?? null;
        }

        return null;
    }
}
