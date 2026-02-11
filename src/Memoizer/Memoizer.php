<?php

namespace Livewire\Blaze\Memoizer;

use Livewire\Blaze\Nodes\ComponentNode;
use Livewire\Blaze\Nodes\TextNode;
use Livewire\Blaze\Nodes\Node;
use Livewire\Blaze\BlazeConfig;
use Livewire\Blaze\Support\ComponentSource;

class Memoizer
{
    protected $compileNode;

    protected BlazeConfig $config;

    public function __construct(callable $compileNode, BlazeConfig $config)
    {
        $this->compileNode = $compileNode;
        $this->config = $config;
    }

    public function isMemoizable(Node $node): bool
    {
        if (! $node instanceof ComponentNode) {
            return false;
        }

        try {
            $source = new ComponentSource($node->name);

            if (! $source->exists()) {
                return false;
            }

            // Component-level @blaze(memo: ...) takes priority over path config
            $memo = $source->directives->blaze('memo');

            if (! is_null($memo)) {
                return $memo;
            }

            // Use path-based default
            return $this->config->shouldMemoize($source->path);

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

        $output = '<' . '?php $blaze_memoized_key = \Livewire\Blaze\Memoizer\Memo::key("' . $name . '", ' . $attributes . '); ?>';
        $output .= '<' . '?php if (! \Livewire\Blaze\Memoizer\Memo::has($blaze_memoized_key)) : ?>';
        $output .= '<' . '?php ob_start(); ?>';
        $output .= ($this->compileNode)($node);
        $output .= '<' . '?php \Livewire\Blaze\Memoizer\Memo::put($blaze_memoized_key, ob_get_clean()); ?>';
        $output .= '<' . '?php endif; ?>';
        $output .= '<' . '?php echo \Livewire\Blaze\Memoizer\Memo::get($blaze_memoized_key); ?>';

        return new TextNode($output);
    }
}
