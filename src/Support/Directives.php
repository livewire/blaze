<?php

namespace Livewire\Blaze\Support;

use Illuminate\Support\Arr;
use Livewire\Blaze\BladeService;
use Livewire\Blaze\Compiler\ArrayParser;

class Directives
{
    public function __construct(
        protected string $content,
    ) {
    }

    public function has(string $name): bool
    {
        $result = false;
        
        BladeService::compileDirective($this->content, $name, function () use (&$result) {
            $result = true;
        });
        
        return $result;
    }

    public function get(string $name): ?string
    {
        $result = null;

        BladeService::compileDirective($this->content, $name, function ($expression) use (&$result) {
            $result = $expression;
        });
        
        return $result;
    }

    public function array(string $name): array|null
    {
        $expression = $this->get($name);

        return $expression ? ArrayParser::parse($expression) : null;
    }

    /**
     * Get the variable names declared by @props.
     *
     * @return string[]
     */
    public function props(): array
    {
        return Arr::map($this->array('props') ?? [], fn ($key, $value) => is_int($key) ? $value : $key);
    }

    public function blaze(?string $param = null): mixed
    {
        if (is_null($param) && $this->has('blaze')) {
            return true;
        }

        $expression = $this->get('blaze');

        if ($expression === null) {
            return null;
        }

        $params = Utils::parseBlazeDirective($expression);

        return $params[$param] ?? null;
    }
}
