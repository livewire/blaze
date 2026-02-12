<?php

namespace Livewire\Blaze\Nodes;

use Livewire\Blaze\Support\Utils;

/**
 * Represents an <x-slot> tag in the AST.
 */
class SlotNode extends Node
{
    /** @var Attribute[] */
    public array $attributes = [];

    public function __construct(
        public string $name,
        public string $attributeString = '',
        public string $slotStyle = 'standard',
        public array $children = [],
        public string $prefix = 'x-slot',
        public bool $closeHasName = false,
    ) {
        $attributes = Utils::parseAttributeStringToArray($this->attributeString);

        foreach ($attributes as $key => $attribute) {
            $this->attributes[$key] = new Attribute(
                name: $attribute['name'],
                value: $attribute['value'],
                propName: $key,
                dynamic: $attribute['isDynamic'] || str_contains($attribute['original'], '{{'),
                prefix: \Illuminate\Support\Str::match('/^(:\$?)/', $attribute['original']),
                quotes: $attribute['quotes'],
            );
        }
    }

    /** {@inheritdoc} */
    public function render(): string
    {
        if ($this->slotStyle === 'short') {
            $output = "<{$this->prefix}:{$this->name}";

            if (! empty($this->attributeString)) {
                $output .= " {$this->attributeString}";
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

        if (! empty($this->attributeString)) {
            $output .= " {$this->attributeString}";
        }

        $output .= '>';

        $output .= $this->content();

        $output .= "</{$this->prefix}>";

        return $output;
    }

    /**
     * Render the slot's children to a string.
     */
    public function content(): string
    {
        return join('', array_map(fn ($child) => $child->render(), $this->children));
    }
}
