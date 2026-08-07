<?php

namespace Livewire\Blaze\Support;

use Livewire\Blaze\BladeService;
use Livewire\Blaze\Parser\Parser;

class ComponentRepository
{
    protected array $components = [];

    public function __construct(
        protected BladeService $blade,
        protected Parser $parser,
    ) {
    }

    public function get(string $name): ?ComponentSource
    {
        if (array_key_exists($name, $this->components)) {
            return $this->components[$name];
        }

        $path = $this->blade->componentNameToPath($name);

        if (! file_exists($path)) {
            return $this->components[$name] = null;
        }

        $ast = $this->parser->parse(file_get_contents($path), $path);

        return $this->components[$name] = new ComponentSource($name, $path, $ast);
    }
}