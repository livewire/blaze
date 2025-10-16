<?php

namespace Livewire\Blaze\Memoizer;

use Livewire\Blaze\Exceptions\LeftoverPlaceholdersException;
use Livewire\Blaze\Exceptions\InvalidPureUsageException;
use Livewire\Blaze\Support\AttributeParser;
use Livewire\Blaze\Events\ComponentFolded;
use Livewire\Blaze\Nodes\ComponentNode;
use Illuminate\Support\Facades\Event;
use Livewire\Blaze\Nodes\TextNode;
use Livewire\Blaze\Nodes\SlotNode;
use Livewire\Blaze\Nodes\Node;

class Memoizer
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

    public function isMemoizable(Node $node): bool
    {
        if (! $node instanceof ComponentNode) {
            return false;
        }

        try {
            $componentPath = ($this->componentNameToPath)($node->name);

            if (empty($componentPath) || ! file_exists($componentPath)) {
                return false;
            }

            $source = file_get_contents($componentPath);

            return (bool) preg_match('/^\s*(?:\/\*.*?\*\/\s*)*@pure/s', $source);

        } catch (\Exception $e) {
            return false;
        }
    }

    public function memoize(Node $node): Node
    {
        if (! $node instanceof ComponentNode) {
            return $node;
        }

        if (! $node->selfClosing) {
            return $node;
        }

        if (! $this->isMemoizable($node)) {
            return $node;
        }

        $name = $node->name;
        $attributes = $node->getAttributesAsRuntimeArrayString();

        $output = '<' . '?php $blaze_memoized_key = \Livewire\Blaze\Memoizer\Memo::key("' . $name . '", ' . $attributes . '); ?>\n';
        $output .= '<' . '?php if (! \Livewire\Blaze\Memoizer\Memo::has($blaze_memoized_key)) : ?>';
        $output .= '<' . '?php ob_start(); ?>';
        $output .= $node->render();
        $output .= '<' . '?php \Livewire\Blaze\Memoizer\Memo::put($blaze_memoized_key, ob_get_clean()); ?>';
        $output .= '<' . '?php endif; ?>';
        $output .= '<' . '?php echo \Livewire\Blaze\Memoizer\Memo::get($blaze_memoized_key); ?>';

        return new TextNode($output);
    }
}
