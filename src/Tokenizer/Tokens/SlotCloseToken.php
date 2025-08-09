<?php

namespace Livewire\Blaze\Tokenizer\Tokens;

class SlotCloseToken extends Token
{
    public function __construct(
        public ?string $name = null,
        public string $prefix = 'x-',
    ) {}

    public function getType(): string
    {
        return 'slot_close';
    }

    public function toArray(): array
    {
        return [
            'type' => $this->getType(),
            'name' => $this->name,
        ];
    }
}