<?php

namespace Livewire\Blaze\Tokenizer\Tokens;

class TagSelfCloseToken extends Token
{
    public function __construct(
        public string $name,
        public string $prefix,
        public string $namespace = '',
        public string $attributes = '',
    ) {}

    public function getType(): string
    {
        return 'tag_self_close';
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