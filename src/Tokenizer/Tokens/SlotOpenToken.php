<?php

namespace Livewire\Blaze\Tokenizer\Tokens;

class SlotOpenToken extends Token
{
    public function __construct(
        public ?string $name = null,
        public string $attributes = '',
        public string $slotStyle = 'standard',
    ) {}

    public function getType(): string
    {
        return 'slot_open';
    }

    public function toArray(): array
    {
        return [
            'type' => $this->getType(),
            'name' => $this->name,
            'attributes' => $this->attributes,
            'slot_style' => $this->slotStyle,
        ];
    }
}