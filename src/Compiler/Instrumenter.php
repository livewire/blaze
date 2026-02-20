<?php

namespace Livewire\Blaze\Compiler;

use Livewire\Blaze\Config;
use Livewire\Blaze\Parser\Nodes\ComponentNode;
use Livewire\Blaze\Parser\Nodes\Node;
use Livewire\Blaze\Parser\Nodes\TextNode;
use Livewire\Blaze\Support\ComponentSource;

/**
 * Wraps every component's compiled output with profiler timer calls.
 *
 * This runs as the final step in the AST pipeline, AFTER folder, memoizer,
 * and compiler. It wraps the call site — not the function body — so the
 * timer captures initialization, require_once, pushData, the render itself,
 * and popData. This gives us unified timing for both Blaze-compiled and
 * standard Blade components.
 */
class Instrumenter
{
    public function __construct(
        protected Config $config,
    ) {
    }

    /**
     * Wrap a compiled component node's output with timer start/stop calls.
     */
    public function instrument(Node $node, string $componentName): Node
    {
        $isBlade = $node instanceof ComponentNode;
        $strategy = $isBlade ? 'blade' : $this->resolveStrategy($componentName);

        $output = $node->render();
        $escapedName = addslashes($componentName);

        $wrapped = '<'.'?php $__blaze->debugger->startTimer(\''.$escapedName.'\', \''.$strategy.'\'); ?>'
            .$output
            .'<'.'?php $__blaze->debugger->stopTimer(\''.$escapedName.'\'); ?>';

        return new TextNode($wrapped);
    }

    /**
     * Determine the optimization strategy configured for a Blaze component.
     */
    protected function resolveStrategy(string $componentName): string
    {
        $source = new ComponentSource($componentName);

        if (! $source->exists()) {
            return 'compiled';
        }

        $fold = $source->directives->blaze('fold') ?? $this->config->shouldFold($source->path);
        $memo = $source->directives->blaze('memo') ?? $this->config->shouldMemoize($source->path);

        $strategy = 'compiled';

        if ($fold) {
            $strategy .= '+fold';
        }

        if ($memo) {
            $strategy .= '+memo';
        }

        return $strategy;
    }
}
