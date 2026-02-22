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
    public function instrument(Node $node, string $componentName, ?string $strategy = null): Node
    {
        $source = new ComponentSource($componentName);

        if ($strategy === null) {
            $isBlade = $node instanceof ComponentNode;
            $strategy = $isBlade ? 'blade' : $this->resolveStrategy($source);
        }

        $file = $source->exists() ? $this->relativePath($source->path) : null;

        $output = $node->render();
        $escapedName = addslashes($componentName);
        $fileArg = $file !== null ? ', \''.addslashes($file).'\'' : '';

        $wrapped = '<'.'?php $__blaze->debugger->startTimer(\''.$escapedName.'\', \''.$strategy.'\''.$fileArg.'); ?>'
            .$output
            .'<'.'?php $__blaze->debugger->stopTimer(\''.$escapedName.'\'); ?>';

        return new TextNode($wrapped);
    }

    /**
     * Determine the optimization strategy configured for a Blaze component.
     */
    protected function resolveStrategy(ComponentSource $source): string
    {
        if (! $source->exists()) {
            return 'compiled';
        }

        $memo = $source->directives->blaze('memo') ?? $this->config->shouldMemoize($source->path);

        $strategy = 'compiled';

        if ($memo) {
            $strategy .= '+memo';
        }

        return $strategy;
    }

    /**
     * Strip the base path prefix to produce a short relative path.
     *
     * Tries the raw path first (preserves vendor/ symlink structure), then
     * falls back to extracting a meaningful suffix for external packages.
     */
    protected function relativePath(string $absolutePath): string
    {
        $base = base_path().'/';

        // Try raw path first (preserves vendor/ symlink structure).
        if (str_starts_with($absolutePath, $base)) {
            return substr($absolutePath, strlen($base));
        }

        // Try resolved path (follows symlinks).
        $resolved = realpath($absolutePath) ?: $absolutePath;

        if (str_starts_with($resolved, $base)) {
            return substr($resolved, strlen($base));
        }

        // Extract from resources/views/ for external packages.
        if (preg_match('#(/resources/views/.+)$#', $absolutePath, $m)) {
            return ltrim($m[1], '/');
        }

        return basename($absolutePath);
    }
}
