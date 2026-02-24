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
class Profiler
{
    public function __construct(
        protected Config $config,
    ) {
    }

    /**
     * Wrap a compiled component node's output with timer start/stop calls.
     */
    public function profile(Node $node, string $componentName, ?string $strategy = null): Node
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

        return 'compiled';
    }

    /**
     * Wrap a view's compiled output with timer start/stop calls.
     *
     * Returns the output unchanged when the path belongs to a component
     * template directory (those already have call-site timers from profile()).
     */
    public function profileView(string $output, string $path, string $source): string
    {
        if ($this->isComponentTemplatePath($path)) {
            return $output;
        }

        $viewName = $this->viewNameFromPath($path);

        if ($viewName !== null) {
            $safe = addslashes($viewName);
            $nameExpr = "'{$safe}'";
        } elseif (str_contains($source, '$layout->viewContext')) {
            // Livewire layout wrapper: resolve the layout name at runtime.
            $nameExpr = '\'layout:\' . ($layout->view ?? \'unknown\')';
        } else {
            // Livewire/Volt: resolve component name at runtime from shared view data.
            $nameExpr = '($__blaze->debugger->resolveViewName() ?? \''.addslashes(pathinfo($path, PATHINFO_FILENAME)).'\')';
        }

        $relativePath = addslashes($this->relativePath($path));

        return '<'.'?php $__blazeViewName = '.$nameExpr.'; $__blaze->debugger->startTimer($__blazeViewName, \'view\', \''.$relativePath.'\'); ?>'
            .$output
            .'<'.'?php $__blaze->debugger->stopTimer($__blazeViewName); ?>';
    }

    /**
     * Extract a human-readable view name from a file path.
     *
     * Returns null when the path can't be resolved to a meaningful name
     * (e.g. Livewire SFC / Volt hash paths in storage/).
     */
    protected function viewNameFromPath(string $path): ?string
    {
        $resolved = realpath($path) ?: $path;

        if (preg_match('#/resources/views/(.+?)\.blade\.php$#', $resolved, $matches)) {
            $name = str_replace('/', '.', $matches[1]);

            return preg_replace('/\.index$/', '', $name);
        }

        return null;
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

    /**
     * Check if a path belongs to a Blade component template directory.
     *
     * Component templates already have call-site timers from profile(),
     * so they don't need view-level timers.
     */
    protected function isComponentTemplatePath(string $path): bool
    {
        $resolved = realpath($path) ?: $path;

        $dirs = [resource_path('views/components')];

        foreach (app('blade.compiler')->getAnonymousComponentPaths() as $registration) {
            $dirs[] = $registration['path'];
        }

        foreach ($dirs as $dir) {
            $normalizedDir = rtrim(realpath($dir) ?: $dir, '/').'/';

            if (str_starts_with($resolved, $normalizedDir) || str_starts_with($path, $normalizedDir)) {
                return true;
            }
        }

        return false;
    }
}
