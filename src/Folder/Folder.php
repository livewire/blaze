<?php

namespace Livewire\Blaze\Folder;

use Livewire\Blaze\Nodes\ComponentNode;
use Livewire\Blaze\Nodes\TextNode;
use Livewire\Blaze\Nodes\Node;

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
            // Extract slot content and replace with placeholders
            $slotPlaceholders = [];
            $processedNode = $this->replaceSlotContentWithPlaceholders($node, $slotPlaceholders);

            // Convert the processed node back to Blade source
            $bladeSource = ($this->renderNodes)([$processedNode]);

            // Render the Blade source through Blade's renderer
            $renderedHtml = ($this->renderBlade)($bladeSource);

            // Restore slot placeholders back to original content
            $finalHtml = $this->restoreSlotPlaceholders($renderedHtml, $slotPlaceholders);

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

    protected function restoreSlotPlaceholders(string $html, array $slotPlaceholders): string
    {
        foreach ($slotPlaceholders as $placeholder => $originalContent) {
            $html = str_replace($placeholder, $originalContent, $html);
        }

        return $html;
    }
}
