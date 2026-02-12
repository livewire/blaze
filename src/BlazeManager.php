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

    public function compile(string $template): string
    {
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
        $directives = new Directives($template);

        if ($directives->blaze()) {
            $output = $this->wrapper->wrap($output, $path, $template);
        }

        BladeService::deleteTemporaryCacheDirectory();

        return $output;
    }

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

    public function flushFoldedEvents()
    {
        return tap($this->foldedEvents, function ($events) {
            $this->foldedEvents = [];

            return $events;
        });
    }

    public function collectAndAppendFrontMatter($template, $callback)
    {
        $this->flushFoldedEvents();

        $output = $callback($template);

        $frontmatter = (new FrontMatter)->compileFromEvents(
            $this->flushFoldedEvents()
        );

        return $frontmatter.$output;
    }

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

    public function render(array $nodes): string
    {
        return implode('', array_map(fn ($n) => $n->render(), $nodes));
    }

    public function enable()
    {
        $this->enabled = true;
    }

    public function disable()
    {
        $this->enabled = false;
    }

    public function debug()
    {
        $this->debug = true;
    }

    public function startFolding(): void
    {
        $this->folding = true;
    }

    public function stopFolding(): void
    {
        $this->folding = false;
    }

    public function isEnabled()
    {
        return $this->enabled;
    }

    public function isDisabled()
    {
        return ! $this->enabled;
    }

    public function isDebugging()
    {
        return $this->debug;
    }

    public function isFolding(): bool
    {
        return $this->folding;
    }

    public function optimize(): Config
    {
        return $this->config;
    }
}
