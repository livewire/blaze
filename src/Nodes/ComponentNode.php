<?php

namespace Livewire\Blaze\Nodes;

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
        $namedSlotNames = [];

        foreach ($this->children as $child) {
            if ($child instanceof SlotNode) {
                $slotName = $child->name;
                if (!empty($slotName) && $slotName !== 'slot') {
                    $slotContent = $renderNodes($child->children);
                    $slotPlaceholders['NAMED_SLOT_' . $slotName] = $slotContent;
                    $namedSlotNames[] = $slotName;
                } else {
                    foreach ($child->children as $grandChild) {
                        $defaultSlotChildren[] = $grandChild;
                    }
                }
            } else {
                $defaultSlotChildren[] = $child;
            }
        }

        // Emit real <x-slot> placeholder nodes for named slots and separate with zero-output PHP
        $count = count($namedSlotNames);
        foreach ($namedSlotNames as $index => $name) {
            if ($index > 0) {
                $processedNode->children[] = new TextNode('<?php /*blaze_sep*/ ?>');
            }
            $processedNode->children[] = new SlotNode(
                name: $name,
                attributes: '',
                slotStyle: 'standard',
                children: [new TextNode('NAMED_SLOT_' . $name)],
                prefix: 'x-slot',
            );
        }

        $defaultPlaceholder = null;
        if (!empty($defaultSlotChildren)) {
            if ($count > 0) {
                // Separate last named slot from default content with zero-output PHP
                $processedNode->children[] = new TextNode('<?php /*blaze_sep*/ ?>');
            }
            $defaultPlaceholder = 'SLOT_PLACEHOLDER_' . count($slotPlaceholders);
            $renderedDefault = $renderNodes($defaultSlotChildren);
            $slotPlaceholders[$defaultPlaceholder] = ($count > 0) ? trim($renderedDefault) : $renderedDefault;
            $processedNode->children[] = new TextNode($defaultPlaceholder);
        } else {
            $processedNode->children[] = new TextNode('');
        }

        $restore = function (string $renderedHtml) use ($slotPlaceholders, $attributePlaceholders, $defaultPlaceholder): string {
            // Replace slot placeholders first
            foreach ($slotPlaceholders as $placeholder => $content) {
                if ($placeholder === $defaultPlaceholder) {
                    // Trim whitespace immediately around the default placeholder position
                    $pattern = '/>\s*' . preg_quote($placeholder, '/') . '\s*</';
                    $renderedHtml = preg_replace($pattern, '>'.$content.'<', $renderedHtml);
                } else {
                    $renderedHtml = str_replace($placeholder, $content, $renderedHtml);
                }
            }
            // Restore attribute placeholders
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