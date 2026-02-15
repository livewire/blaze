<?php

namespace Livewire\Blaze\Parser\Nodes;

use Illuminate\Support\Str;
use Livewire\Blaze\Support\AttributeParser;
use Livewire\Blaze\Support\Utils;

/**
 * Represents an <x-component> or <flux:component> tag in the AST.
 */
class ComponentNode extends Node
{
    /** @var Attribute[] */
    public array $attributes = [];

    /** Pre-computed by the Walker before children are compiled to TextNodes. */
    public bool $hasAwareDescendants = false;

    public function __construct(
        public string $name,
        public string $prefix,
        public string $attributeString = '',
        public array $children = [],
        public bool $selfClosing = false,
        public array $parentsAttributes = [],
    ) {
        $attributes = Utils::parseAttributeStringToArray($this->attributeString);

        foreach ($attributes as $key => $attribute) {
            $this->attributes[$key] = new Attribute(
                name: $attribute['name'],
                value: $attribute['value'],
                propName: $key,
                dynamic: $attribute['isDynamic'] || str_contains($attribute['original'], '{{'),
                prefix: Str::match('/^(::|\:\$|:)/', $attribute['original']),
                quotes: $attribute['quotes'],
            );
        }
    }

    /**
     * Resolve the slot name, handling both short (<x-slot:name>) and standard syntax.
     */
    protected function resolveSlotName(SlotNode $slot): string
    {
        if (! empty($slot->name)) {
            return $slot->name;
        }

        if (preg_match('/(?:^|\s)name\s*=\s*["\']([^"\']+)["\']/', $slot->attributeString, $matches)) {
            return $matches[1];
        }

        return 'slot';
    }

    /**
     * Set the accumulated parent component attributes for @aware resolution.
     */
    public function setParentsAttributes(array $parentsAttributes): void
    {
        $this->parentsAttributes = $parentsAttributes;
    }

    /** {@inheritdoc} */
    public function render(): string
    {
        $name = $this->stripNamespaceFromName($this->name, $this->prefix);

        $output = "<{$this->prefix}{$name}";

        foreach ($this->attributes as $attribute) {
            if ($attribute->value === true) {
                $output .= ' '.$attribute->name;
            } else {
                $output .= ' '.$attribute->prefix.$attribute->name;
                
                if ($attribute->prefix !== ':$') {
                    $output .= '='.$attribute->quotes.$attribute->value.$attribute->quotes;
                }
            }
        }

        if ($this->selfClosing) {
            return $output.' />';
        }

        $output .= '>';

        // Iterate over original children to preserve structure
        foreach ($this->children as $child) {
            $output .= $child->render();
        }

        $output .= "</{$this->prefix}{$name}>";

        return $output;
    }

    /**
     * Convert attributes to a PHP array string for runtime evaluation.
     */
    public function getAttributesAsRuntimeArrayString(): string
    {
        $attributeParser = new AttributeParser;

        $attributesArray = $attributeParser->parseAttributeStringToArray($this->attributeString);

        return $attributeParser->parseAttributesArrayToRuntimeArrayString($attributesArray);
    }

    /**
     * Strip the namespace prefix from a component name for tag rendering.
     */
    protected function stripNamespaceFromName(string $name, string $prefix): string
    {
        $prefixes = [
            'flux:' => ['namespace' => 'flux::'],
            'x:' => ['namespace' => ''],
            'x-' => ['namespace' => ''],
        ];
        if (isset($prefixes[$prefix])) {
            $namespace = $prefixes[$prefix]['namespace'];
            if (! empty($namespace) && str_starts_with($name, $namespace)) {
                return substr($name, strlen($namespace));
            }
        }

        return $name;
    }
}
