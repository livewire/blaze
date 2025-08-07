<?php

namespace Livewire\Blaze\Folder;

use Livewire\Blaze\Nodes\TagNode;
use Livewire\Blaze\Nodes\TextNode;
use Livewire\Blaze\Nodes\Node;

class Folder
{
    protected $renderBlade;
    protected $renderNode;

    public function __construct(callable $renderBlade, callable $renderNode)
    {
        $this->renderBlade = $renderBlade;
        $this->renderNode = $renderNode;
    }

    public function isFoldable(Node $node): bool
    {
        return $node instanceof TagNode;
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
