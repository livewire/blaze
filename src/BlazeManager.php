<?php

namespace Livewire\Blaze;

use Illuminate\Support\Facades\Event;
use Livewire\Blaze\Compiler\Wrapper;
use Livewire\Blaze\Compiler\Compiler;
use Livewire\Blaze\Directive\BlazeDirective;
use Livewire\Blaze\Events\ComponentFolded;
use Livewire\Blaze\Folder\Folder;
use Livewire\Blaze\Memoizer\Memoizer;
use Livewire\Blaze\Nodes\ComponentNode;
use Livewire\Blaze\Parser\Parser;
use Livewire\Blaze\Tokenizer\Tokenizer;
use Livewire\Blaze\Walker\Walker;
use Livewire\Blaze\Support\Directives;

class BlazeManager
{
    protected $foldedEvents = [];

    protected $enabled = true;

    protected $throw = false;

    protected $debug = false;

    protected $folding = false;

    protected $expiredMemo = [];

    public function __construct(
        protected Tokenizer $tokenizer,
        protected Parser $parser,
        protected Walker $walker,
        protected Compiler $compiler,
        protected Folder $folder,
        protected Memoizer $memoizer,
        protected Wrapper $wrapper,
        protected Config $config,
    ) {
        Event::listen(ComponentFolded::class, function (ComponentFolded $event) {
            $this->foldedEvents[] = $event;
        });
    }

    /**
     * Compile a Blade template through the full Blaze pipeline.
     */
    public function compile(string $template): string
    {
        $source = $template;

        $template = BladeService::preStoreUncompiledBlocks($template);
        $template = BladeService::compileComments($template);

        $dataStack = [];

        $tokens = $this->tokenizer->tokenize($template);
        $ast = $this->parser->parse($tokens);
        $ast = $this->walker->walk(
            nodes: $ast,
            preCallback: function ($node) use (&$dataStack) {
                if ($dataStack && $node instanceof ComponentNode) {
                    $node->setParentsAttributes(array_merge(...$dataStack));
                }

                if (($node instanceof ComponentNode) && $node->children) {
                    $dataStack[] = $node->attributes;
                }

                return $node;
            },
            postCallback: function ($node) use (&$dataStack) {
                if (($node instanceof ComponentNode) && $node->children) {
                    array_pop($dataStack);
                }

                $node = $this->folder->fold($node);
                $node = $this->memoizer->memoize($node);
                $node = $this->compiler->compile($node);

                return $node;
            },
        );

        $output = $this->render($ast);

        $path = app('blade.compiler')->getPath();
        $directives = new Directives($source);

        if ($path && $directives->blaze()) {
            $output = $this->wrapper->wrap($output, $path, $source);
        }

        BladeService::deleteTemporaryCacheDirectory();

        return $output;
    }

    /**
     * Compile a template within an @unblaze block (no folding, no wrapping).
     */
    public function compileForUnblaze(string $template): string
    {
        $template = BladeService::preStoreUncompiledBlocks($template);
        $template = BladeService::compileComments($template);

        $tokens = $this->tokenizer->tokenize($template);

        $ast = $this->parser->parse($tokens);

        $ast = $this->walker->walk(
            nodes: $ast,
            preCallback: fn ($node) => $node,
            postCallback: function ($node) use (&$dataStack) {
                $node = $this->memoizer->memoize($node);
                $node = $this->compiler->compile($node);

                return $node;
            },
        );

        $output = $this->render($ast);

        return $output;
    }

    /**
     * Compile for folding context - only tag compiler and component compiler.
     * No folding or memoization to avoid infinite recursion.
     */
    public function compileForFolding(string $template): string
    {
        $source = $template;

        $template = BladeService::preStoreUncompiledBlocks($template);
        $template = BladeService::compileComments($template);

        $tokens = $this->tokenizer->tokenize($template);

        $ast = $this->parser->parse($tokens);

        $ast = $this->walker->walk(
            nodes: $ast,
            preCallback: fn ($node) => $node,
            postCallback: function ($node) use (&$dataStack) {
                return $this->compiler->compile($node);
            },
        );

        $output = $this->render($ast);

        $currentPath = app('blade.compiler')->getPath();
        $params = BlazeDirective::getParameters($template);

        if ($currentPath && $params !== null) {
            $output = $this->wrapper->wrap($output, $currentPath, $source);
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
        $path = $view->getPath();

        if (isset($this->expiredMemo[$path])) {
            return $this->expiredMemo[$path];
        }

        $compiler = $view->getEngine()->getCompiler();
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
        return $this->enabled;
    }

    /**
     * Check if Blaze compilation is disabled.
     */
    public function isDisabled()
    {
        return ! $this->enabled;
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
        return $this->debug;
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
}
