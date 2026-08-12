<?php

namespace Livewire\Blaze\Parser\Nodes;

/**
 * Represents an <x-component> or <flux:component> tag in the AST.
 */
class ComponentNode extends Node
{
    /** Pre-computed by the Walker before children are compiled to TextNodes. */
    public bool $hasAwareDescendants = false;

    public function __construct(
        public string $name,
        public string $prefix,
        public string $attributeString = '',
        public array $children = [],
        public bool $selfClosing = false,
        public array $parentsAttributes = [],
        /** @var Attribute[] */
        public array $attributes = [],
    ) {
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
            $output .= ' ' . $attribute->render();
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
