<?php

namespace Livewire\Blaze\Memoizer;

use Livewire\Blaze\Nodes\ComponentNode;
use Livewire\Blaze\Nodes\TextNode;
use Livewire\Blaze\Nodes\Node;
use Livewire\Blaze\Directive\BlazeDirective;

class Memoizer
{
    protected $componentNameToPath;
    protected $compileNode;

    protected $getOptimizeBuilder;

    public function __construct(callable $componentNameToPath, callable $compileNode, callable $getOptimizeBuilder)
    {
        $this->componentNameToPath = $componentNameToPath;
        $this->compileNode = $compileNode;
        $this->getOptimizeBuilder = $getOptimizeBuilder;
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

            $directiveParameters = BlazeDirective::getParameters($source);

            // Get path-based default for memo
            $optimizeBuilder = ($this->getOptimizeBuilder)();
            $pathMemoDefault = $optimizeBuilder->shouldMemo($componentPath);

            // Component-level @blaze(memo: ...) takes priority over path config
            if (! is_null($directiveParameters) && isset($directiveParameters['memo'])) {
                return $directiveParameters['memo'];
            }

            // Use path-based default if available
            if ($pathMemoDefault !== null) {
                return $pathMemoDefault;
            }

            // Final fallback: false (memoization is opt-in)
            return $directiveParameters['memo'] ?? false;

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
