<?php

namespace Livewire\Blaze\Support;

use Livewire\Blaze\BladeService;

class Directives
{
    public function __construct(
        protected ComponentSource $source,
    ) {
    }

    public function has(string $name): bool
    {
        $result = false;
        
        BladeService::compileDirective($this->source->content, $name, function () use (&$result) {
            $result = true;
        });
        
        return $result;
    }

    public function get(string $name): ?string
    {
        $result = null;

        BladeService::compileDirective($this->source->content, $name, function ($expression) use (&$result) {
            $result = $expression;
        });
        
        return $result;
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

        if ($expression === null) {
            return null;
        }

        $params = Utils::parseBlazeDirective($expression);

        return $params[$param] ?? null;
    }
}
