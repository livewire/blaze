<?php

namespace Livewire\Blaze\Folder;

use Livewire\Blaze\Nodes\ComponentNode;
use Livewire\Blaze\Nodes\TextNode;
use Livewire\Blaze\Nodes\Node;

class Folder
{
    protected $renderBlade;
    protected $renderNode;
    protected $componentNameToPath;

    public function __construct(callable $renderBlade, callable $renderNode, callable $componentNameToPath)
    {
        $this->renderBlade = $renderBlade;
        $this->renderNode = $renderNode;
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
            // Convert the node back to Blade source using the renderNode callback
            $bladeSource = ($this->renderNode)($node);

            // Render the Blade source through Blade's renderer
            $renderedHtml = ($this->renderBlade)($bladeSource);

            // Return a TextNode containing the folded HTML
            return new TextNode($renderedHtml);

        } catch (\Exception $e) {
            // If folding fails for any reason, return the original node
            return $node;
        }
    }
}
