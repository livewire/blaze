<?php

namespace Livewire\Blaze\Parser\Tokens;

class TagOpenToken extends Token
{
    public function __construct(
        public string $name,
        public string $prefix,
        public string $namespace = '',
        public string $attributes = '',
    ) {}

    public function getType(): string
    {
        return 'tag_open';
    }

    public function toArray(): array
    {
        return [
            'type' => $this->getType(),
            'name' => $this->name,
            'prefix' => $this->prefix,
            'namespace' => $this->namespace,
            'attributes' => $this->attributes,
        ];
    }
}