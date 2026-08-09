<?php

namespace Livewire\Blaze;

use Illuminate\Support\Facades\Event;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\View;
use Livewire\Blaze\Compiler\Wrapper;
use Livewire\Blaze\Compiler\Compiler;
use Livewire\Blaze\Compiler\Profiler;
use Livewire\Blaze\Runtime\BlazeRuntime;
use Livewire\Blaze\Events\ComponentFolded;
use Livewire\Blaze\Folder\Folder;
use Livewire\Blaze\Memoizer\Memoizer;
use Livewire\Blaze\Parser\Nodes\ComponentNode;
use Livewire\Blaze\Parser\Nodes\DirectiveNode;
use Livewire\Blaze\Parser\Parser;
use Livewire\Blaze\Parser\Tokenizer;
use Livewire\Blaze\Parser\Walker;
use Livewire\Blaze\Parser\Nodes\SlotNode;
use Livewire\Blaze\Support\AttributeParser;
use Livewire\Blaze\Support\ComponentRepository;
use Livewire\Blaze\Parser\Nodes\Node;
use Livewire\Blaze\Parser\Nodes\TextNode;
use Illuminate\View\Factory;

class BlazeManager
{
    protected ?bool $enabled = null;
    protected ?bool $debug = null;

    protected bool $throw = false;
    protected bool $folding = false;
    
    protected $foldedEvents = [];
    protected $expiredMemo = [];

    protected Parser $parser;
    protected Compiler $compiler;
    protected Folder $folder;
    protected Memoizer $memoizer;
    protected Wrapper $wrapper;
    protected Profiler $instrumenter;
    protected BladeRenderer $renderer;
    protected ComponentRepository $components;

    public function __construct(
        protected Config $config,
        protected BladeCompiler $bladeCompiler,
        protected BlazeRuntime $runtime,
        protected BladeService $blade,
        protected Factory $factory,
    ) {
        $this->renderer = new BladeRenderer($bladeCompiler, $factory, $this->runtime, $this);
        $this->parser = new Parser(new Tokenizer($this->blade), new AttributeParser($this->blade));
        $this->components = new ComponentRepository($this->blade, $this->parser);
        $this->compiler = new Compiler($config, $this->blade, $this, $this->components);
        $this->folder = new Folder($config, $this->blade, $this->renderer, $this, $this->components);
        $this->memoizer = new Memoizer($config, $this->compiler, $this->blade, $this, $this->components);
        $this->wrapper = new Wrapper($this->blade, $this);
        $this->instrumenter = new Profiler($config, $this->blade, $this, $this->components);

        Event::listen(ComponentFolded::class, function (ComponentFolded $event) {
            $this->foldedEvents[] = $event;
        });
    }

    /**
     * Compile a Blade template through the full Blaze pipeline.
     */
    public function compile(string $source, ?string $path = null): string
    {
        $dataStack = [];

        $template = $this->parser->parse($source, $path);

        $ast = Walker::walk(
            nodes: $template->nodes,
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

        if ($path && ($template->directives->blaze() || $this->config->shouldCompile($path))) {
            $output = $this->render($this->wrapper->wrap($ast, $path));
        } elseif ($this->isDebugging() && ! $this->isFolding() && $path) {
            $output = $this->instrumenter->profileView($output, $path, $source);
        }

        return $output;
    }

    /**
     * Compile for folding context - only tag compiler and component compiler.
     * No folding or memoization to avoid infinite recursion.
     */
    public function compileForFolding(string $source, ?string $path = null): string
    {
        $template = $this->parser->parse($source, $path);

        $currentUnblazeToken = null;

        $ast = Walker::walk(
            nodes: $template->nodes,
            preCallback: function (Node $node) use (&$currentUnblazeToken) {
                if ($node instanceof DirectiveNode && $node->is('unblaze')) {
                    $currentUnblazeToken = str()->random(10);
                    $tag = '[STARTCOMPILEDUNBLAZE:' . $currentUnblazeToken . ']';
                    $content = '<?php \Livewire\Blaze\Unblaze::storeScope("' . $currentUnblazeToken . '", ' . $node->expression . '); ?>';

                    return new TextNode($tag . $content);
                }

                if ($node instanceof DirectiveNode && $node->is('endunblaze') && $currentUnblazeToken) {
                    $tag = '[ENDCOMPILEDUNBLAZE:' . $currentUnblazeToken . ']';

                    $currentUnblazeToken = null;

                    return new TextNode($tag);
                }

                if ($currentUnblazeToken) {
                    Unblaze::storeReplacement($currentUnblazeToken, $node->render());

                    return new TextNode('');
                }
            },
            postCallback: function ($node) {
                return $this->compiler->compile($node);
            },
        );

        $output = $this->render($ast);

        if (! $path) {
            return $output;
        }

        $shouldWrap = $this->config->shouldFold($path)
            || $this->config->shouldMemoize($path)
            || $this->config->shouldCompile($path);

        if ($template->directives->blaze() || $shouldWrap) {
            $output = $this->render($this->wrapper->wrap($ast, $path));
        }

        return $output;
    }

    /**
     * Compile a template within an @unblaze block (no folding, no wrapping).
     */
    public function compileForUnblaze(string $source): string
    {
        $template = $this->parser->parse($source);

        $ast = Walker::walk(
            nodes: $template->nodes,
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

        return $this->render($ast);
    }

    /**
     * Compile a template for debug-only mode (Blaze disabled).
     *
     * Parses the template to find components and wraps them with timer
     * calls, but does NOT fold, memoize, or compile — Blade handles that.
     * Also injects view-level timers for non-wrapped views.
     */
    public function compileForDebug(string $source, ?string $path = null): string
    {
        $template = $this->parser->parse($source, $path);

        $ast = Walker::walk(
            nodes: $template->nodes,
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
    public function collectAndAppendFrontMatter(string $source, callable $callback)
    {
        $this->flushFoldedEvents();

        $output = $callback($source);

        $frontmatter = (new FrontMatter)->compileFromEvents(
            $this->flushFoldedEvents()
        );

        return $frontmatter.$output;
    }

    /**
     * Check if a view's compiled output contains stale folded component references.
     */
    public function viewContainsExpiredFrontMatter(View $view): bool
    {
        $engine = $view->getEngine();
        $path = $view->getPath();

        if (isset($this->expiredMemo[$path])) {
            return $this->expiredMemo[$path];
        }

        if (! $engine instanceof CompilerEngine) {
            return $this->expiredMemo[$path] = false;
        }

        $compiler = $engine->getCompiler();
        $compiled = $compiler->getCompiledPath($path);
        $expired = $compiler->isExpired($path) ? false : (new FrontMatter)->containsExpiredFoldedDependencies($compiled);

        return $this->expiredMemo[$path] = $expired;
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
        return $this->enabled ??= config('blaze.enabled', true);
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
        return $this->debug ??= config('blaze.debug', false);
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
                $component = $this->components->get($child->name);

                if (str_ends_with($child->name, 'delegate-component')) {
                    return true;
                }

                if (! $component) {
                    return false;
                }

                if ($component->template->directives->has('aware')) {
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

    /**
     * Reset all per-request mutable state.
     */
    public function flushState(): void
    {
        $this->foldedEvents = [];
        $this->expiredMemo = [];
    }
}
