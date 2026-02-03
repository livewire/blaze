<?php

namespace Livewire\Blaze\Support;

class Directives
{
    public function __construct(
        protected string $source,
    ) {
    }

    public function has(string $name): bool
    {
        //
    }

    public function get(string $name): ?string
    {
        //
    }

    public function array(string $name): array|null
    {
        $expression = $this->get($name);

        return $expression ? Utils::parseArrayContent($expression) : null;
    }

    public function blaze(?string $param = null): mixed
    {
        if ($this->has('blaze') && is_null($param)) {
            return true;
        }

        $expression = $this->get('blaze');
        $params = Utils::parseBlazeDirective($expression);

        return $params[$param] ?? null;
    }
}