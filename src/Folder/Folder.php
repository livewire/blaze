<?php

namespace Livewire\Blaze\Folder;

use Livewire\Blaze\Nodes\TextNode;
use Livewire\Blaze\Nodes\Node;
use Livewire\Blaze\Nodes\ComponentNode;
use Livewire\Blaze\Nodes\SlotNode;
use Livewire\Blaze\Events\ComponentFolded;
use Livewire\Blaze\Exceptions\InvalidPureUsageException;
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
                // Validate @pure usage before folding
                $source = file_get_contents($componentPath);
                $this->validatePureComponent($source, $componentPath);

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

            // Get the component template and pre-process named slots
            $componentPath = ($this->componentNameToPath)($node->name);
            $templateSource = file_get_contents($componentPath);

            // Replace named slot variables in the template with placeholders before rendering
            $templateWithPlaceholders = $this->replaceNamedSlotVariables($templateSource, $slotPlaceholders);

            // Convert the processed node back to Blade source
            $bladeSource = ($this->renderNodes)([$processedNode]);

            // Replace the component tag with the pre-processed template
            $childContent = '';
            if (!empty($processedNode->children) && $processedNode->children[0] instanceof TextNode) {
                $childContent = $processedNode->children[0]->content;
            }
            $bladeSource = str_replace('<x-' . $node->name . '>' . $childContent . '</x-' . $node->name . '>', $templateWithPlaceholders, $bladeSource);

            // Render the Blade source through Blade's renderer
            $renderedHtml = ($this->renderBlade)($bladeSource);

            // Restore placeholders back to original content
            $finalHtml = $this->restoreSlotPlaceholders($renderedHtml, $slotPlaceholders);
            $finalHtml = $this->restoreAttributePlaceholders($finalHtml, $attributePlaceholders);

            // Return a TextNode containing the folded HTML
            return new TextNode($finalHtml);

        } catch (InvalidPureUsageException $e) {
            // Re-throw validation exceptions
            throw $e;
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

        // Separate named slots from default slot content
        $namedSlots = [];
        $defaultSlotChildren = [];

        foreach ($node->children as $child) {
            if ($child instanceof SlotNode) {
                // SlotNode represents <x-slot> elements
                $slotName = $child->name; // SlotNode has a name property
                if ($slotName && $slotName !== 'slot') {
                    // Named slot - render its content
                    $slotContent = ($this->renderNodes)($child->children);
                    $namedSlots[$slotName] = $slotContent;
                } else {
                    // Default slot - treat as default slot content
                    $defaultSlotChildren[] = $child;
                }
            } else {
                // Regular content - part of default slot
                $defaultSlotChildren[] = $child;
            }
        }

        // Store named slots in a special format that the restore function can handle
        foreach ($namedSlots as $slotName => $content) {
            $slotPlaceholders['NAMED_SLOT_' . $slotName] = $content;
        }

        // Handle default slot content (including any remaining children)
        if (!empty($defaultSlotChildren)) {
            $slotContent = ($this->renderNodes)($defaultSlotChildren);
            $placeholder = 'SLOT_PLACEHOLDER_' . count($slotPlaceholders);
            $slotPlaceholders[$placeholder] = $slotContent;
            $processedNode->children[] = new TextNode($placeholder);
        } else {
            // Even if no default content, add empty placeholder to maintain structure
            $processedNode->children[] = new TextNode('');
        }

        return $processedNode;
    }

    protected function extractSlotName(string $attributes): ?string
    {
        // Extract name attribute from slot attributes
        if (preg_match('/name\s*=\s*["\']([^"\']+)["\']/', $attributes, $matches)) {
            return $matches[1];
        }
        return null;
    }

    protected function replaceNamedSlotVariables(string $templateSource, array $slotPlaceholders): string
    {
        foreach ($slotPlaceholders as $placeholder => $content) {
            if (str_starts_with($placeholder, 'NAMED_SLOT_')) {
                $slotName = strtolower(str_replace('NAMED_SLOT_', '', $placeholder));
                // Replace the variable reference in the template with the placeholder
                $templateSource = str_replace('{{ $' . $slotName . ' }}', $placeholder, $templateSource);
            } else {
                // This is a regular slot placeholder - replace {{ $slot }} with it
                $templateSource = str_replace('{{ $slot }}', $placeholder, $templateSource);
            }
        }
        return $templateSource;
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
            // Replace all placeholders (both named slot and regular slot placeholders) with their content
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

    protected function validatePureComponent(string $source, string $componentPath): void
    {
        $problematicPatterns = [
            '@aware' => 'forAware',
            '\\$errors' => 'forErrors',
            'session\\(' => 'forSession',
            '@error\\(' => 'forError',
            '@csrf' => 'forCsrf',
            'auth\\(\\)' => 'forAuth',
            'request\\(\\)' => 'forRequest',
            'old\\(' => 'forOld',
        ];

        foreach ($problematicPatterns as $pattern => $factoryMethod) {
            if (preg_match('/' . $pattern . '/', $source)) {
                throw InvalidPureUsageException::{$factoryMethod}($componentPath);
            }
        }
    }
}
