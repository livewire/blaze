<?php

namespace Livewire\Blaze\Support;

use Livewire\Blaze\Compiler\ArrayParser;
use Livewire\Blaze\Compiler\DirectiveCompiler;

/**
 * Extracts and queries Blade directives from component source content.
 */
class Directives
{
    /** @var array<string, string|null> */
    protected array $parsed;

    protected string $content;

    public function __construct(string $content)
    {
        $this->content = $content;
        $this->content = preg_replace(LaravelRegex::BLADE_COMMENT, '', $this->content);
        $this->content = preg_replace(LaravelRegex::VERBATIM_BLOCK, '', $this->content);
        $this->content = preg_replace(LaravelRegex::PHP_BLOCK, '', $this->content);

        $this->parsed = $this->parseKnownDirectives();
    }

    /**
     * Check if a directive exists in the content.
     */
    public function has(string $name): bool
    {
        $this->resolveIfNeeded($name);

        return $this->parsed[$name] !== null;
    }

    /**
     * Get the expression of a directive, or null if not found.
     */
    public function get(string $name): ?string
    {
        $this->resolveIfNeeded($name);

        return $this->parsed[$name];
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

    /**
     * If a directive hasn't been resolved yet, do a one-off compile
     * for it and cache the result (or null if absent).
     */
    protected function resolveIfNeeded(string $name): void
    {
        if (array_key_exists($name, $this->parsed)) {
            return;
        }

        $result = null;

        DirectiveCompiler::make()->directive($name, function ($expression) use (&$result) {
            $result = $expression;

            return '';
        })->compile($this->content);

        $this->parsed[$name] = $result;
    }

    /**
     * Extract all known Blaze directives in a single DirectiveCompiler pass.
     */
    protected function parseKnownDirectives(): array
    {
        $directives = [];

        $capture = function (string $name) use (&$directives) {
            return function ($expression) use ($name, &$directives) {
                $directives[$name] = $expression;

                return '';
            };
        };

        DirectiveCompiler::make()
            ->directive('blaze', $capture('blaze'))
            ->directive('props', $capture('props'))
            ->directive('aware', $capture('aware'))
            ->compile($this->content);

        return $directives;
    }
}
