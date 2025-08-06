<?php

namespace Livewire\Blaze\Parser\Tokens;

class SlotCloseToken extends Token
{
    public function __construct(
        public ?string $name = null,
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