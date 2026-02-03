<?php

namespace Livewire\Blaze\Nodes;

class SlotNode extends Node
{
    public function __construct(
        public string $name,
        // TODO: Slots should have attributes as an array as well...
        public string $attributes = '',
        public string $slotStyle = 'standard',
        public array $children = [],
        public string $prefix = 'x-slot',
        public bool $closeHasName = false,
    ) {}

    public function render(): string
    {
        if ($this->slotStyle === 'short') {
            $output = "<{$this->prefix}:{$this->name}";

            if (! empty($this->attributes)) {
                $output .= " {$this->attributes}";
            }

            $output .= '>';

            foreach ($this->children as $child) {
                $output .= $child instanceof Node ? $child->render() : (string) $child;
            }

            // Short syntax may close with </prefix> or </prefix:name>...
            $output .= $this->closeHasName
                ? "</{$this->prefix}:{$this->name}>"
                : "</{$this->prefix}>";

            return $output;
        }

        $output = "<{$this->prefix}";

        if (! empty($this->name)) {
            $output .= ' name="' . $this->name . '"';
        }

        if (! empty($this->attributes)) {
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