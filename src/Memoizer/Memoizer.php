<?php

namespace Livewire\Blaze\Memoizer;

use Livewire\Blaze\Parser\Nodes\ComponentNode;
use Livewire\Blaze\Parser\Nodes\TextNode;
use Livewire\Blaze\Parser\Nodes\Node;
use Livewire\Blaze\Config;
use Livewire\Blaze\Support\ComponentSource;
use Livewire\Blaze\Compiler\Compiler;

/**
 * Wraps compiled component output with runtime memoization logic.
 */
class Memoizer
{
    public function __construct(
        protected Config $config,
        protected Compiler $compiler,
    ) {
    }

    /**
     * Wrap a self-closing component with memoization output buffering.
     */
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

        $compiled = $this->compiler->compile($node)->render();

        $isDebugging = app('blaze')->isDebugging() && ! app('blaze')->isFolding();

        $output = '<' . '?php $blaze_memoized_key = \Livewire\Blaze\Memoizer\Memo::key("' . $name . '", ' . $attributes . '); ?>';
        $output .= '<' . '?php if ($blaze_memoized_key !== null && \Livewire\Blaze\Memoizer\Memo::has($blaze_memoized_key)) : ?>';
        $output .= $isDebugging ? '<' . '?php ($__blaze ?? app(\'blaze.runtime\'))->debugger->recordMemoHit(\'' . addslashes($name) . '\'); ?>' : '';
        $output .= '<' . '?php echo \Livewire\Blaze\Memoizer\Memo::get($blaze_memoized_key); ?>';
        $output .= '<' . '?php else : ?>';
        $output .= '<' . '?php ob_start(); ?>';
        $output .= $compiled;
        $output .= '<' . '?php $blaze_memoized_html = ob_get_clean(); ?>';
        $output .= '<' . '?php if ($blaze_memoized_key !== null) { \Livewire\Blaze\Memoizer\Memo::put($blaze_memoized_key, $blaze_memoized_html); } ?>';
        $output .= '<' . '?php echo $blaze_memoized_html; ?>';
        $output .= '<' . '?php endif; ?>';

        return new TextNode($output);
    }

    /**
     * Check if a node should be memoized based on directive and config settings.
     */
    protected function isMemoizable(Node $node): bool
    {
        if (! $node instanceof ComponentNode) {
            return false;
        }

        $source = new ComponentSource($node->name);

        if (! $source->exists()) {
            return false;
        }

        if (! is_null($memo = $source->directives->blaze('memo'))) {
            return $memo;
        }

        return $this->config->shouldMemoize($source->path);
    }
}
