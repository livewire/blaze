<?php

namespace Livewire\Blaze\Nodes;

class SlotNode extends Node
{
    public function __construct(
        public string $name,
        public string $attributes = '',
        public string $slotStyle = 'standard',
        public array $children = [],
        public string $prefix = 'x-slot',
        public bool $closeHasName = false,
    ) {}

    public function getType(): string
    {
        return 'slot';
    }

    public function toArray(): array
    {
        return [
            'type' => $this->getType(),
            'name' => $this->name,
            'attributes' => $this->attributes,
            'slot_style' => $this->slotStyle,
            'prefix' => $this->prefix,
            'close_has_name' => $this->closeHasName,
            'children' => array_map(fn($child) => $child instanceof Node ? $child->toArray() : $child, $this->children),
        ];
    }

    public function render(): string
    {
        if ($this->slotStyle === 'short') {
            $output = "<{$this->prefix}:{$this->name}";
            if (!empty($this->attributes)) {
                $output .= " {$this->attributes}";
            }
            $output .= '>';
            foreach ($this->children as $child) {
                $output .= $child instanceof Node ? $child->render() : (string) $child;
            }
            // Short syntax may close with </prefix> or </prefix:name>
            $output .= $this->closeHasName
                ? "</{$this->prefix}:{$this->name}>"
                : "</{$this->prefix}>";
            return $output;
        }

        $output = "<{$this->prefix}";
        if (!empty($this->name)) {
            $output .= ' name="' . $this->name . '"';
        }
        if (!empty($this->attributes)) {
            $output .= " {$this->attributes}";
        }
        $output .= '>';
        foreach ($this->children as $child) {
            $output .= $child instanceof Node ? $child->render() : (string) $child;
        }
        $output .= "</{$this->prefix}>";
        return $output;
    }
}