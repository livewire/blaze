<?php

namespace Livewire\Blaze\Folder;

use Livewire\Blaze\Nodes\TextNode;
use Livewire\Blaze\Nodes\Node;
use Livewire\Blaze\Nodes\ComponentNode;
use Livewire\Blaze\Events\ComponentFolded;
use Illuminate\Support\Facades\Event;

class Folder
{
    protected $renderBlade;
    protected $renderNodes;
    protected $componentNameToPath;

    public function __construct(callable $renderBlade, callable $renderNodes, callable $componentNameToPath)
    {
        $this->renderBlade = $renderBlade;
        $this->renderNodes = $renderNodes;
        $this->componentNameToPath = $componentNameToPath;
    }

    public function isFoldable(Node $node): bool
    {
        if (!$node instanceof ComponentNode) {
            return false;
        }

        try {
            $componentPath = ($this->componentNameToPath)($node->name);

            if (empty($componentPath) || !file_exists($componentPath)) {
                return false;
            }

            $source = file_get_contents($componentPath);

            // Check for @pure directive at the top of the file (allowing for whitespace and comments)
            return (bool) preg_match('/^\s*(?:\/\*.*?\*\/\s*)*@pure/s', $source);

        } catch (\Exception $e) {
            return false;
        }
    }

    public function fold(Node $node): Node
    {
        // If not foldable, return the node unchanged
        if (! $this->isFoldable($node)) {
            return $node;
        }

        try {
            // Dispatch event for component folding
            $componentPath = ($this->componentNameToPath)($node->name);
            if (file_exists($componentPath)) {
                Event::dispatch(new ComponentFolded(
                    name: $node->name,
                    path: $componentPath,
                    filemtime: filemtime($componentPath)
                ));
            }

            // Extract slot content and replace with placeholders
            $slotPlaceholders = [];
            $attributePlaceholders = [];

            $processedNode = $this->replaceSlotContentWithPlaceholders($node, $slotPlaceholders);
            $processedNode = $this->replaceDynamicAttributesWithPlaceholders($processedNode, $attributePlaceholders);

            // Convert the processed node back to Blade source
            $bladeSource = ($this->renderNodes)([$processedNode]);

            // Render the Blade source through Blade's renderer
            $renderedHtml = ($this->renderBlade)($bladeSource);

            // Restore placeholders back to original content
            $finalHtml = $this->restoreSlotPlaceholders($renderedHtml, $slotPlaceholders);
            $finalHtml = $this->restoreAttributePlaceholders($finalHtml, $attributePlaceholders);

            // Return a TextNode containing the folded HTML
            return new TextNode($finalHtml);

        } catch (\Exception $e) {
            // If folding fails for any reason, return the original node
            return $node;
        }
    }

    protected function replaceSlotContentWithPlaceholders(ComponentNode $node, array &$slotPlaceholders): ComponentNode
    {
        // Clone the node to avoid modifying the original
        $processedNode = new ComponentNode(
            name: $node->name,
            prefix: $node->prefix,
            attributes: $node->attributes,
            children: [],
            selfClosing: $node->selfClosing
        );

        // If no children, return as-is
        if (empty($node->children)) {
            return $processedNode;
        }

        // Render all children as slot content using renderNodes
        $slotContent = ($this->renderNodes)($node->children);

        // Create single placeholder for entire slot content
        $placeholder = 'SLOT_PLACEHOLDER_' . count($slotPlaceholders);
        $slotPlaceholders[$placeholder] = $slotContent;

        // Replace with a single text placeholder
        $processedNode->children[] = new TextNode($placeholder);

        return $processedNode;
    }

    protected function replaceDynamicAttributesWithPlaceholders(ComponentNode $node, array &$attributePlaceholders): ComponentNode
    {
        // If no attributes, return as-is
        if (empty($node->attributes)) {
            return $node;
        }

        $attributes = $this->parseDynamicAttributes($node->attributes, $attributePlaceholders);

        // Return new node with processed attributes
        return new ComponentNode(
            name: $node->name,
            prefix: $node->prefix,
            attributes: $attributes,
            children: $node->children,
            selfClosing: $node->selfClosing
        );
    }

    protected function parseDynamicAttributes(string $attributesString, array &$attributePlaceholders): string
    {
        // Handle :attribute="value" and :$variable syntax
        $attributesString = preg_replace_callback('/(\s*):([a-zA-Z0-9_-]+)\s*=\s*("[^"]*"|\$[a-zA-Z0-9_]+)/', function ($matches) use (&$attributePlaceholders) {
            $whitespace = $matches[1];
            $attributeName = $matches[2];
            $attributeValue = $matches[3];

            // Create placeholder for the dynamic value
            $placeholderKey = 'ATTR_PLACEHOLDER_' . count($attributePlaceholders);
            $placeholder = '"' . $placeholderKey . '"';
            $attributePlaceholders[$placeholderKey] = $attributeValue;

            // Return the attribute with placeholder value
            return $whitespace . $attributeName . '=' . $placeholder;
        }, $attributesString);

        // Handle short syntax :$variable (extract attribute name from variable name)
        $attributesString = preg_replace_callback('/(\s*):(\$[a-zA-Z0-9_]+)/', function ($matches) use (&$attributePlaceholders) {
            $whitespace = $matches[1];
            $variable = $matches[2]; // e.g., "$type"

            // Extract attribute name from variable name (remove $)
            $attributeName = ltrim($variable, '$');

            // Create placeholder for the dynamic value
            $placeholderKey = 'ATTR_PLACEHOLDER_' . count($attributePlaceholders);
            $placeholder = '"' . $placeholderKey . '"';
            $attributePlaceholders[$placeholderKey] = '"' . $variable . '"';

            // Return the attribute with placeholder value
            return $whitespace . $attributeName . '=' . $placeholder;
        }, $attributesString);

        // Handle attributes containing {{ }} expressions (echo attributes)
        $attributesString = preg_replace_callback('/(\s*)([a-zA-Z0-9_-]+)\s*=\s*("(?:[^"\\\\]|\\\\.)*\{\{[^}]*\}\}(?:[^"\\\\]|\\\\.)*")/', function ($matches) use (&$attributePlaceholders) {
            $whitespace = $matches[1];
            $attributeName = $matches[2];
            $attributeValue = $matches[3]; // includes quotes and {{ }} content

            // Create placeholder for the dynamic value
            $placeholderKey = 'ATTR_PLACEHOLDER_' . count($attributePlaceholders);
            $placeholder = '"' . $placeholderKey . '"';
            $attributePlaceholders[$placeholderKey] = $attributeValue;

            // Return the attribute with placeholder value
            return $whitespace . $attributeName . '=' . $placeholder;
        }, $attributesString);

        return $attributesString;
    }

    protected function restoreSlotPlaceholders(string $html, array $slotPlaceholders): string
    {
        foreach ($slotPlaceholders as $placeholder => $originalContent) {
            $html = str_replace($placeholder, $originalContent, $html);
        }

        return $html;
    }

    protected function restoreAttributePlaceholders(string $html, array $attributePlaceholders): string
    {
        foreach ($attributePlaceholders as $placeholder => $originalValue) {
            // Convert back to Blade syntax - remove quotes and wrap variables in {{ }}
            $restoredValue = $originalValue;

            // If it's a simple variable like "$type", convert to "{{ $type }}"
            if (preg_match('/^"\$[a-zA-Z0-9_]+"$/', $originalValue)) {
                $variable = trim($originalValue, '"');
                $restoredValue = '"{{ ' . $variable . ' }}"';
            }
            // If it's already a quoted string with {{ }} expressions, use as-is
            elseif (preg_match('/^".*\{\{.*\}\}.*"$/', $originalValue)) {
                $restoredValue = $originalValue;
            }

            // Remove quotes from placeholder when replacing, since HTML attributes are already quoted
            $html = str_replace('"' . $placeholder . '"', $restoredValue, $html);
        }

        return $html;
    }
}
