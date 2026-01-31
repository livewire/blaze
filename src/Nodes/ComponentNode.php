<?php

namespace Livewire\Blaze\Nodes;

use Livewire\Blaze\Support\AttributeParser;

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
            'children' => array_map(fn ($child) => $child instanceof Node ? $child->toArray() : $child, $this->children),
            'self_closing' => $this->selfClosing,
            'parents_attributes' => $this->parentsAttributes,
        ];

        return $array;
    }

    public function render(): string
    {
        $name = $this->stripNamespaceFromName($this->name, $this->prefix);

        $output = "<{$this->prefix}{$name}";
        if (! empty($this->attributes)) {
            $output .= " {$this->attributes}";
        }
        if ($this->selfClosing) {
            return $output.' />';
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
        $attributeParser = new AttributeParser;

        $attributesArray = $attributeParser->parseAttributeStringToArray($this->attributes);

        return $attributeParser->parseAttributesArrayToRuntimeArrayString($attributesArray);
    }

    public function replaceDynamicPortionsWithPlaceholders(callable $renderNodes): array
    {
        $attributePlaceholders = [];
        $attributeNameToPlaceholder = [];
        $processedAttributes = (new AttributeParser)->parseAndReplaceDynamics(
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
                if (! empty($slotName) && $slotName !== 'slot') {
                    $slotContent = $renderNodes($child->children);
                    $slotPlaceholders['NAMED_SLOT_'.$slotName] = $slotContent;
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
                children: [new TextNode('NAMED_SLOT_'.$name)],
                prefix: 'x-slot',
            );
        }

        $defaultPlaceholder = null;
        if (! empty($defaultSlotChildren)) {
            if ($count > 0) {
                // Separate last named slot from default content with zero-output PHP...
                $processedNode->children[] = new TextNode('<?php /*blaze_sep*/ ?>');
            }
            $defaultPlaceholder = 'SLOT_PLACEHOLDER_'.count($slotPlaceholders);
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
                    // Use preg_replace_callback to avoid $N backreference interpretation in content
                    // (e.g., "$49.00" would have "$49" interpreted as capture group 49)
                    $pattern = '/\s*'.preg_quote($placeholder, '/').'\s*/';
                    $renderedHtml = preg_replace_callback($pattern, fn () => $content, $renderedHtml);
                } else {
                    $renderedHtml = str_replace($placeholder, $content, $renderedHtml);
                }
            }

            // Process fenced attributes (from $attributes rendering during folding)
            $renderedHtml = self::processFencedAttributes($renderedHtml, $attributePlaceholders);

            // Restore any remaining attribute placeholders (those not inside fences)
            foreach ($attributePlaceholders as $placeholder => $original) {
                $renderedHtml = str_replace($placeholder, $original, $renderedHtml);
            }

            return $renderedHtml;
        };

        return [$processedNode, $slotPlaceholders, $restore, $attributeNameToPlaceholder, $attributeNameToOriginal, $this->attributes];
    }

    public function mergeAwareAttributes(array $awareAttributes): void
    {
        $attributeParser = new AttributeParser;

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
                    'original' => $attributeName.'="'.$attributeValue.'"',
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
        $this->attributes = $attributeParser->parseAttributesArrayToPropString($attributes);
    }

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

    /**
     * Process fenced attributes from folding, converting dynamic ones to conditional PHP.
     */
    protected static function processFencedAttributes(string $html, array $attributePlaceholders): string
    {
        return preg_replace_callback(
            '/<!--BLAZE_ATTR:([a-zA-Z0-9_.:-]+)-->(.+?)<!--\/BLAZE_ATTR-->/',
            function ($matches) use ($attributePlaceholders) {
                $name = $matches[1];
                $content = $matches[2];

                // Check if content contains a placeholder (dynamic attribute)
                if (preg_match('/ATTR_PLACEHOLDER_\d+/', $content, $placeholderMatch)) {
                    $placeholder = $placeholderMatch[0];
                    $original = $attributePlaceholders[$placeholder] ?? null;

                    // Only generate conditional PHP when the ENTIRE value is a single {{ expression }}.
                    // This handles boolean semantics (e.g. disabled="{{ $isDisabled }}" → omit when false).
                    // Mixed content like wire:key="opt-{{ $a }}-{{ $b }}" should NOT match—it always
                    // has content and gets restored as-is for normal Blade compilation.
                    if ($original && preg_match('/^\s*\{\{\s*(.+?)\s*\}\}\s*$/s', $original, $exprMatch)) {
                        $expression = trim($exprMatch[1]);

                        return self::generateConditionalAttribute($name, $expression);
                    }
                }

                // Static attribute - return content without fence markers
                return $content;
            },
            $html
        );
    }

    /**
     * Generate conditional PHP for a dynamic attribute that handles boolean semantics.
     */
    protected static function generateConditionalAttribute(string $name, string $expression): string
    {
        // Match Laravel's behavior: x-data and wire:* get empty string for true, others get key name
        $trueValue = ($name === 'x-data' || str_starts_with($name, 'wire:'))
            ? "''"
            : "'".addslashes($name)."'";

        return '<'.'?php if (($__blazeAttr = '.$expression.') !== false && !is_null($__blazeAttr)): ?'.'>'
             .' '.$name.'="<'.'?php echo e($__blazeAttr === true ? '.$trueValue.' : $__blazeAttr); ?'.'>"'
             .'<'.'?php endif; ?'.'>';
    }
}
