<?php

namespace Livewire\Blaze\Nodes;

use Livewire\Blaze\Support\AttributeParser;
use Livewire\Blaze\Nodes\SlotNode;
use Livewire\Blaze\Nodes\TextNode;

class ComponentNode extends Node
{
    public function __construct(
        public string $name,
        public string $prefix,
        public string $attributes = '',
        public array $children = [],
        public bool $selfClosing = false,
        public array $parentsAttributes = [],
    ) {}

    public function getType(): string
    {
        return 'component';
    }

    public function setParentsAttributes(array $parentsAttributes): void
    {
        $this->parentsAttributes = $parentsAttributes;
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
            'parents_attributes' => $this->parentsAttributes,
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

    public function getAttributesAsRuntimeArrayString(): string
    {
        $arrayOfAttributes = (new AttributeParser())->parseToArray($this->attributes);

        // $arrayOfAttributes =
        // [
        //     "foo" => array:3 [
        //         "isDynamic" => false
        //         "value" => "bar"
        //         "original" => "foo="bar""
        //     ]
        // ]

        // Now turn this into a runtime array string like this:
        // "['foo' => 'bar']"

        $arrayParts = [];

        foreach ($arrayOfAttributes as $attributeName => $attributeData) {
            if ($attributeData['isDynamic']) {
                $arrayParts[] = "'" . addslashes($attributeName) . "' => " . $attributeData['value'];
                continue;
            }

            $value = $attributeData['value'];

            // Handle different value types
            if (is_bool($value)) {
                $valueString = $value ? 'true' : 'false';
            } elseif (is_string($value)) {
                $valueString = "'" . addslashes($value) . "'";
            } elseif (is_null($value)) {
                $valueString = 'null';
            } else {
                $valueString = (string) $value;
            }

            $arrayParts[] = "'" . addslashes($attributeName) . "' => " . $valueString;
        }

        return '[' . implode(', ', $arrayParts) . ']';
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

        // Map attribute name => original dynamic content (if dynamic)
        $attributeNameToOriginal = [];
        foreach ($attributeNameToPlaceholder as $name => $placeholder) {
            if (isset($attributePlaceholders[$placeholder])) {
                $attributeNameToOriginal[$name] = $attributePlaceholders[$placeholder];
            }
        }

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

        // Emit real <x-slot> placeholder nodes for named slots and separate with zero-output PHP...
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
                // Separate last named slot from default content with zero-output PHP...
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
            // Replace slot placeholders first...
            foreach ($slotPlaceholders as $placeholder => $content) {
                if ($placeholder === $defaultPlaceholder) {
                    // Trim whitespace immediately around the default placeholder position...
                    $pattern = '/\s*' . preg_quote($placeholder, '/') . '\s*/';
                    $renderedHtml = preg_replace($pattern, $content, $renderedHtml);
                } else {
                    $renderedHtml = str_replace($placeholder, $content, $renderedHtml);
                }
            }
            // Restore attribute placeholders...
            foreach ($attributePlaceholders as $placeholder => $original) {
                $renderedHtml = str_replace($placeholder, $original, $renderedHtml);
            }
            return $renderedHtml;
        };

        return [$processedNode, $slotPlaceholders, $restore, $attributeNameToPlaceholder, $attributeNameToOriginal, $this->attributes];
    }

    public function mergeAwareAttributes(array $awareAttributes): void
    {
        $attributeParser = new AttributeParser();

        // Attributes are a string of attributes in the format:
        // `name1="value1" name2="value2" name3="value3"`
        // So we need to convert that attributes string to an array of attributes with the format:
        // [
        //     'name' => [
        //         'isDynamic' => true,
        //         'value' => '$name',
        //         'original' => ':name="$name"',
        //     ],
        // ]
        $attributes = $attributeParser->parseAttributeStringToArray($this->attributes);

        $parentsAttributes = [];

        // Parents attributes are an array of attributes strings in the same format 
        // as above so we also need to convert them to an array of attributes...
        foreach ($this->parentsAttributes as $parentAttributes) {
            $parentsAttributes[] = $attributeParser->parseAttributeStringToArray($parentAttributes);
        }

        // Now we can take the aware attributes and merge them with the components attributes...
        foreach ($awareAttributes as $key => $value) {
            // As `$awareAttributes` is an array of attributes, which can either have just
            // a value, which is the attribute name, or a key-value pair, which is the
            // attribute name and a default value...
            if (is_int($key)) {
                $attributeName = $value;
                $attributeValue = null;
                $defaultValue = null;
            } else {
                $attributeName = $key;
                $attributeValue = $value;
                $defaultValue = [
                    'isDynamic' => false,
                    'value' => $attributeValue,
                    'original' => $attributeName . '="' . $attributeValue . '"',
                ];
            }

            if (isset($attributes[$attributeName])) {
                continue;
            }

            // Loop through the parents attributes in reverse order so that the last parent
            // attribute that matches the attribute name is used...
            foreach (array_reverse($parentsAttributes) as $parsedParentAttributes) {
                // If an attribute is found, then use it and stop searching...
                if (isset($parsedParentAttributes[$attributeName])) {
                    $attributes[$attributeName] = $parsedParentAttributes[$attributeName];
                    break;
                }
            }

            // If the attribute is not set then fall back to using the aware value.
            // We need to add it in the same format as the other attributes...
            if (! isset($attributes[$attributeName]) && $defaultValue !== null) {
                $attributes[$attributeName] = $defaultValue;
            }
        }

        // Convert the parsed attributes back to a string with the original format:
        // `name1="value1" name2="value2" name3="value3"`
        $this->attributes = $attributeParser->parseAttributesArrayToString($attributes);
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