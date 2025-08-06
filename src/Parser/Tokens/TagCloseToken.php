<?php

namespace Livewire\Blaze\Parser\Tokens;

class TagCloseToken extends Token
{
    public function __construct(
        public string $name,
        public string $prefix,
        public string $namespace = '',
    ) {}

    public function getType(): string
    {
        return 'tag_close';
    }

    public function toArray(): array
    {
        return [
            'type' => $this->getType(),
            'name' => $this->name,
            'prefix' => $this->prefix,
            'namespace' => $this->namespace,
        ];
    }
}