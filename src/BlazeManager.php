<?php

namespace Livewire\Blaze;

use Illuminate\Support\Facades\Event;
use Illuminate\View\Engines\CompilerEngine;
use Livewire\Blaze\Compiler\Wrapper;
use Livewire\Blaze\Compiler\Compiler;
use Livewire\Blaze\Compiler\Profiler;
use Livewire\Blaze\Directive\BlazeDirective;
use Livewire\Blaze\Events\ComponentFolded;
use Livewire\Blaze\Folder\Folder;
use Livewire\Blaze\Memoizer\Memoizer;
use Livewire\Blaze\Parser\Nodes\ComponentNode;
use Livewire\Blaze\Parser\Parser;
use Livewire\Blaze\Parser\Tokenizer;
use Livewire\Blaze\Parser\Walker;
use Livewire\Blaze\Support\Directives;
use Livewire\Blaze\Support\ComponentSource;
use Livewire\Blaze\Parser\Nodes\SlotNode;

class BlazeManager
{
    protected ?bool $enabled = null;
    protected ?bool $debug = null;

    protected bool $throw = false;
    protected bool $folding = false;
    
    protected $foldedEvents = [];
    protected $expiredMemo = [];

    public function __construct(
        protected Tokenizer $tokenizer,
        protected Parser $parser,
        protected Walker $walker,
        protected Compiler $compiler,
        protected Folder $folder,
        protected Memoizer $memoizer,
        protected Wrapper $wrapper,
        protected Profiler $instrumenter,
        protected Config $config,
    ) {
        Event::listen(ComponentFolded::class, function (ComponentFolded $event) {
            $this->foldedEvents[] = $event;
        });
    }

    /**
     * Compile a Blade template through the full Blaze pipeline.
     */
    public function compile(string $template, ?string $path = null): string
    {
        $source = $template;

        $clean = $template;
        $clean = BladeService::preStoreUncompiledBlocks($clean);
        $clean = BladeService::compileComments($clean);

        $dataStack = [];

        $ast = $this->walker->walk(
            nodes: $this->parser->parse($clean),
            preCallback: function ($node) use (&$dataStack) {
                if ($node instanceof ComponentNode && $node->children) {
                    $dataStack[] = $node->attributes;

                    $node->hasAwareDescendants = $this->hasAwareDescendant($node);
                }

                if ($node instanceof ComponentNode) {
                    $node->setParentsAttributes(array_merge(...$dataStack));
                }

                return $node;
            },
            postCallback: function ($node) use (&$dataStack) {
                if ($node instanceof ComponentNode && $node->children) {
                    array_pop($dataStack);
                }

                $wasComponent = $node instanceof ComponentNode;
                $componentName = $wasComponent ? $node->name : null;

                $beforeFold = $node;
                $node = $this->folder->fold($node);
                $wasFolded = $wasComponent && $node !== $beforeFold;

                $node = $this->memoizer->memoize($node);
                $node = $this->compiler->compile($node);

                if ($wasComponent && $this->isDebugging() && ! $this->isFolding()) {
                    $strategy = $wasFolded ? 'folded' : null;
                    $node = $this->instrumenter->profile($node, $componentName, $strategy);
                }

                return $node;
            },
        );

        $output = $this->render($ast);

        $directives = new Directives($source);

        if ($path && ($directives->blaze() || $this->config->shouldCompile($path))) {
            $output = $this->wrapper->wrap($output, $path, $source);
        } elseif ($this->isDebugging() && ! $this->isFolding() && $path) {
            $output = $this->instrumenter->profileView($output, $path, $source);
        }

        $output = BladeService::restoreRawBlocks($output);

        try {
            BladeService::deleteTemporaryCacheDirectory();
        } catch (\Throwable $e) {
            //
        }

        return $output;
    }

    /**
     * Compile a template within an @unblaze block (no folding, no wrapping).
     */
    public function compileForUnblaze(string $template): string
    {
        $template = BladeService::preStoreUncompiledBlocks($template);
        $template = BladeService::compileComments($template);

        $ast = $this->walker->walk(
            nodes: $this->parser->parse($template),
            preCallback: fn ($node) => $node,
            postCallback: function ($node) {
                $wasComponent = $node instanceof ComponentNode;
                $componentName = $wasComponent ? $node->name : null;

                $node = $this->memoizer->memoize($node);
                $node = $this->compiler->compile($node);

                if ($wasComponent && $this->isDebugging()) {
                    $node = $this->instrumenter->profile($node, $componentName);
                }

                return $node;
            },
        );

        $output = $this->render($ast);

        // We should not restore raw blocks here. Doing so would preemptively
        // flush all raw blocks stored in the original template and they
        // wouldn't be restored in the parent compile() method.

        return $output;
    }

    /**
     * Compile a template for debug-only mode (Blaze disabled).
     *
     * Parses the template to find components and wraps them with timer
     * calls, but does NOT fold, memoize, or compile — Blade handles that.
     * Also injects view-level timers for non-wrapped views.
     */
    public function compileForDebug(string $template, ?string $path = null): string
    {
        $source = $template;

        $clean = $template;
        $clean = BladeService::preStoreUncompiledBlocks($clean);
        $clean = BladeService::compileComments($clean);

        $ast = $this->walker->walk(
            nodes: $this->parser->parse($clean),
            preCallback: fn ($node) => $node,
            postCallback: function ($node) {
                if (! ($node instanceof ComponentNode)) {
                    return $node;
                }

                return $this->instrumenter->profile($node, $node->name, 'blade');
            },
        );

        $output = $this->render($ast);

        if ($path) {
            $output = $this->instrumenter->profileView($output, $path, $source);
        }

        $output = BladeService::restoreRawBlocks($output);

        return $output;
    }

    /**
     * Compile for folding context - only tag compiler and component compiler.
     * No folding or memoization to avoid infinite recursion.
     */
    public function compileForFolding(string $template, ?string $path = null): string
    {
        $source = $template;

        $template = BladeService::preStoreUncompiledBlocks($template);
        $template = BladeService::compileComments($template);

        $ast = $this->walker->walk(
            nodes: $this->parser->parse($template),
            preCallback: fn ($node) => $node,
            postCallback: function ($node) {
                return $this->compiler->compile($node);
            },
        );

        $output = $this->render($ast);

        $output = BladeService::restoreRawBlocks($output);

        if (! $path) {
            return $output;
        }

        $directives = new Directives($source);
        $shouldWrap = $this->config->shouldFold($path)
            || $this->config->shouldMemoize($path)
            || $this->config->shouldCompile($path);

        if ($directives->blaze() || $shouldWrap) {
            $output = $this->wrapper->wrap($output, $path, $source);
        }

        return $output;
    }

    /**
     * Flush and return all collected ComponentFolded events.
     */
    public function flushFoldedEvents()
    {
        return tap($this->foldedEvents, function ($events) {
            $this->foldedEvents = [];

            return $events;
        });
    }

    /**
     * Run a compilation callback and prepend front matter from any folded components.
     */
    public function collectAndAppendFrontMatter($template, $callback)
    {
        $this->flushFoldedEvents();

        $output = $callback($template);

        $frontmatter = (new FrontMatter)->compileFromEvents(
            $this->flushFoldedEvents()
        );

        return $frontmatter.$output;
    }

    /**
     * Check if a view's compiled output contains stale folded component references.
     */
    public function viewContainsExpiredFrontMatter($view): bool
    {
        $engine = $view->getEngine();

        if (! $engine instanceof CompilerEngine) {
            return false;
        }

        $path = $view->getPath();

        if (isset($this->expiredMemo[$path])) {
            return $this->expiredMemo[$path];
        }

        $compiler = $engine->getCompiler();
        $compiled = $compiler->getCompiledPath($path);
        $expired = $compiler->isExpired($path);

        $isExpired = false;

        if (! $expired) {
            $contents = file_get_contents($compiled);

            $isExpired = (new FrontMatter)->sourceContainsExpiredFoldedDependencies($contents);
        }

        $this->expiredMemo[$path] = $isExpired;

        return $isExpired;
    }

    /**
     * Render an array of AST nodes to their string output.
     */
    public function render(array $nodes): string
    {
        return implode('', array_map(fn ($n) => $n->render(), $nodes));
    }

    /**
     * Enable Blaze compilation.
     */
    public function enable()
    {
        $this->enabled = true;
    }

    /**
     * Disable Blaze compilation.
     */
    public function disable()
    {
        $this->enabled = false;
    }

    /**
     * Enable throw mode.
     */
    public function throw()
    {
        $this->throw = true;
    }

    /**
     * Enable debug mode.
     */
    public function debug()
    {
        $this->debug = true;
    }

    /**
     * Mark the beginning of a fold operation.
     */
    public function startFolding(): void
    {
        $this->folding = true;
    }

    /**
     * Mark the end of a fold operation.
     */
    public function stopFolding(): void
    {
        $this->folding = false;
    }

    /**
     * Check if Blaze compilation is enabled.
     */
    public function isEnabled()
    {
        return $this->enabled ?? config('blaze.enabled', true);
    }

    /**
     * Check if Blaze compilation is disabled.
     */
    public function isDisabled()
    {
        return ! $this->isEnabled();
    }

    /**
     * Check if throw mode is active.
     */
    public function shouldThrow()
    {
        return $this->throw;
    }

    /**
     * Check if debug mode is active.
     */
    public function isDebugging()
    {
        return $this->debug ?? config('blaze.debug', false);
    }

    /**
     * Check if a fold operation is currently in progress.
     */
    public function isFolding(): bool
    {
        return $this->folding;
    }

    /**
     * Access the optimization configuration.
     */
    public function optimize(): Config
    {
        return $this->config;
    }

    /**
     * Recursively check if any descendant component uses @aware.
     */
    protected function hasAwareDescendant(ComponentNode|SlotNode $node): bool
    {
        foreach ($node->children as $child) {
            if ($child instanceof ComponentNode) {
                $source = new ComponentSource($child->name);

                if (str_ends_with($child->name, 'delegate-component')) {
                    return true;
                }

                if ($source->directives->has('aware')) {
                    return true;
                }

                if ($this->hasAwareDescendant($child)) {
                    return true;
                }
            } elseif ($child instanceof SlotNode) {
                if ($this->hasAwareDescendant($child)) {
                    return true;
                }
            }
        }

        return false;
    }
}
