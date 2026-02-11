<?php

namespace Livewire\Blaze\Nodes;

use Illuminate\Support\Str;
use Livewire\Blaze\Support\AttributeParser;
use Livewire\Blaze\Support\Utils;

class ComponentNode extends Node
{
    /** @var Attribute[] */
    public array $attributes = [];
    
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
     * Resolve the slot name from a SlotNode.
     * Handles both short syntax (<x-slot:name>) and standard syntax (<x-slot name="name">).
     */
    protected function resolveSlotName(SlotNode $slot): string
    {
        // Short syntax: name is directly on the node
        if (!empty($slot->name)) {
            return $slot->name;
        }
        
        // Standard syntax: extract name from attributes
        if (preg_match('/(?:^|\s)name\s*=\s*["\']([^"\']+)["\']/', $slot->attributeString, $matches)) {
            return $matches[1];
        }
        
        return 'slot';
    }

    public function setParentsAttributes(array $parentsAttributes): void
    {
        $this->parentsAttributes = $parentsAttributes;
    }

    /**
     * Render the component preserving the original structure.
     * Use this for passthrough/round-trip rendering.
     */
    public function render(): string
    {
        $name = $this->stripNamespaceFromName($this->name, $this->prefix);

        $output = "<{$this->prefix}{$name}";

        foreach ($this->attributes as $attribute) {
            if ($attribute->value === true) {
                $output .= ' ' . $attribute->name;
            } else {
                $output .= ' ' . $attribute->prefix . $attribute->name . '=' . $attribute->quotes . $attribute->value . $attribute->quotes;
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

    public function getAttributesAsRuntimeArrayString(): string
    {
        $attributeParser = new AttributeParser;

        $attributesArray = $attributeParser->parseAttributeStringToArray($this->attributeString);

        return $attributeParser->parseAttributesArrayToRuntimeArrayString($attributesArray);
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
    public static function processFencedAttributes(string $html, array $attributePlaceholders): string
    {
        return preg_replace_callback(
            '/<!--BLAZE_ATTR:([a-zA-Z0-9_.:-]+)-->(.+?)<!--\/BLAZE_ATTR-->/',
            function ($matches) use ($attributePlaceholders) {
                $name = $matches[1];
                $content = $matches[2];

                // Check if content contains a placeholder (dynamic attribute)
                if (preg_match('/ATTR_PLACEHOLDER_\d+|BLAZE_PLACEHOLDER_[A-Z0-9]+/', $content, $placeholderMatch)) {
                    $placeholder = $placeholderMatch[0];
                    $original = $attributePlaceholders[$placeholder] ?? null;

                    // Only generate conditional PHP when the ENTIRE value is a single {{ expression }}.
                    // This handles boolean semantics (e.g. disabled="{{ $isDisabled }}" → omit when false).
                    // Mixed content like wire:key="opt-{{ $a }}-{{ $b }}" should NOT match—it always
                    // has content and gets restored as-is for normal Blade compilation.
                    if ($original) {
                        if (preg_match('/^\s*\{\{\s*(.+?)\s*\}\}\s*$/s', $original, $exprMatch)) {
                            $expression = trim($exprMatch[1]);

                            return self::generateConditionalAttribute($name, $expression);
                        }

                        if (! str_contains($original, '{{')) {
                            return self::generateConditionalAttribute($name, trim($original));
                        }
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
