<?php

namespace Livewire\Blaze\Nodes;

use Livewire\Blaze\Nodes\NamedSlotNode;
use Livewire\Blaze\Nodes\DefaultSlotNode;
use Livewire\Blaze\Nodes\SlotNode;
use Livewire\Blaze\Nodes\TextNode;
use Livewire\Blaze\Support\AttributeParser;

class ComponentNode extends Node
{
    public function __construct(
        public string $name,
        public string $prefix,
        public string $attributes = '',
        public array $children = [],
        public bool $selfClosing = false,
    ) {}

    public function getType(): string
    {
        return 'component';
    }

    public function toArray(): array
    {
        $array = [
            'type' => $this->getType(),
            'name' => $this->name,
            'prefix' => $this->prefix,
            'attributes' => $this->attributes,
            'children' => array_map(fn($child) => $child instanceof Node ? $child->toArray() : $child, $this->children),
            'self_closing' => $this->selfClosing,
        ];

        return $array;
    }

    public function render(): string
    {
        $name = $this->stripNamespaceFromName($this->name, $this->prefix);

        $output = "<{$this->prefix}{$name}";
        if (!empty($this->attributes)) {
            $output .= " {$this->attributes}";
        }
        if ($this->selfClosing) {
            return $output . ' />';
        }
        $output .= '>';
        foreach ($this->children as $child) {
            $output .= $child instanceof Node ? $child->render() : (string) $child;
        }
        $output .= "</{$this->prefix}{$name}>";
        return $output;
    }

    public function replaceDynamicPortionsWithPlaceholders(callable $renderNodes): array
    {
        $attributePlaceholders = [];
        $attributeNameToPlaceholder = [];
        $processedAttributes = (new AttributeParser())->parseAndReplaceDynamics(
            $this->attributes,
            $attributePlaceholders,
            $attributeNameToPlaceholder
        );

        $processedNode = new self(
            name: $this->name,
            prefix: $this->prefix,
            attributes: $processedAttributes,
            children: [],
            selfClosing: $this->selfClosing,
        );

        $slotPlaceholders = [];
        $defaultSlotChildren = [];

        foreach ($this->children as $child) {
            if ($child instanceof NamedSlotNode) {
                $slotName = $child->name;
                $slotContent = $renderNodes($child->children);
                $slotPlaceholders['NAMED_SLOT_' . $slotName] = $slotContent;
            } elseif ($child instanceof DefaultSlotNode) {
                foreach ($child->children as $grandChild) {
                    $defaultSlotChildren[] = $grandChild;
                }
            } elseif ($child instanceof SlotNode) {
                $slotName = $child->name;
                if (!empty($slotName) && $slotName !== 'slot') {
                    $slotContent = $renderNodes($child->children);
                    $slotPlaceholders['NAMED_SLOT_' . $slotName] = $slotContent;
                } else {
                    foreach ($child->children as $grandChild) {
                        $defaultSlotChildren[] = $grandChild;
                    }
                }
            } else {
                $defaultSlotChildren[] = $child;
            }
        }

        if (!empty($defaultSlotChildren)) {
            $placeholder = 'SLOT_PLACEHOLDER_' . count($slotPlaceholders);
            $slotPlaceholders[$placeholder] = $renderNodes($defaultSlotChildren);
            $processedNode->children[] = new TextNode($placeholder);
        } else {
            $processedNode->children[] = new TextNode('');
        }

        $restore = function (string $renderedHtml) use ($slotPlaceholders, $attributePlaceholders): string {
            foreach ($slotPlaceholders as $placeholder => $content) {
                $renderedHtml = str_replace($placeholder, $content, $renderedHtml);
            }
            foreach ($attributePlaceholders as $placeholder => $original) {
                $renderedHtml = str_replace($placeholder, $original, $renderedHtml);
            }
            return $renderedHtml;
        };

        return [$processedNode, $slotPlaceholders, $restore, $attributeNameToPlaceholder];
    }

    protected function stripNamespaceFromName(string $name, string $prefix): string
    {
        $prefixes = [
            'flux:' => [ 'namespace' => 'flux::' ],
            'x:' => [ 'namespace' => '' ],
            'x-' => [ 'namespace' => '' ],
        ];
        if (isset($prefixes[$prefix])) {
            $namespace = $prefixes[$prefix]['namespace'];
            if (!empty($namespace) && str_starts_with($name, $namespace)) {
                return substr($name, strlen($namespace));
            }
        }
        return $name;
    }
}