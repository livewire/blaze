<?php

namespace Livewire\Blaze;

use Livewire\Blaze\Compiler\ComponentCompiler;
use Livewire\Blaze\Compiler\TagCompiler;
use Livewire\Blaze\Directive\BlazeDirective;
use Livewire\Blaze\Events\ComponentFolded;
use Livewire\Blaze\Nodes\ComponentNode;
use Livewire\Blaze\Tokenizer\Tokenizer;
use Illuminate\Support\Facades\Event;
use Livewire\Blaze\Memoizer\Memoizer;
use Livewire\Blaze\Walker\Walker;
use Livewire\Blaze\Parser\Parser;
use Livewire\Blaze\Folder\Folder;

class BlazeManager
{
    protected $foldedEvents = [];

    protected $enabled = true;

    protected $debug = false;

    protected $expiredMemo = [];

    public function __construct(
        protected Tokenizer $tokenizer,
        protected Parser $parser,
        protected Walker $walker,
        protected TagCompiler $tagCompiler,
        protected Folder $folder,
        protected Memoizer $memoizer,
        protected ComponentCompiler $componentCompiler,
    ) {
        Event::listen(ComponentFolded::class, function (ComponentFolded $event) {
            $this->foldedEvents[] = $event;
        });
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

        return $frontmatter . $output;
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

    public function compile(string $template): string
    {
        $template = (new BladeService)->preStoreVerbatimBlocks($template);
        $currentPath = app('blade.compiler')->getPath();
        $params = BlazeDirective::getParameters($template);

        // Wrap in function if ANY @blaze directive is present (not just bare @blaze).
        // This ensures fold/memo components have a function fallback if folding fails.
        $shouldWrapInFunction = $params !== null && !empty($currentPath);

        $tokens = $this->tokenizer->tokenize($template);

        $ast = $this->parser->parse($tokens);

        $dataStack = [];

        $ast = $this->walker->walk(
            nodes: $ast,
            preCallback: function ($node) use (&$dataStack) {
                if ($node instanceof ComponentNode) {
                    $node->setParentsAttributes($dataStack);
                }

                if (($node instanceof ComponentNode) && !empty($node->children)) {
                    array_push($dataStack, $node->attributes);
                }

                return $node;
            },
            postCallback: function ($node) use (&$dataStack) {
                if (($node instanceof ComponentNode) && !empty($node->children)) {
                    array_pop($dataStack);
                }

                // Order matters: fold/memo try first, function compile catches failures
                $node = $this->folder->fold($node);
                $node = $this->memoizer->memoize($node);
                $node = $this->tagCompiler->compile($node);

                return $node;
            },
        );

        $output = $this->render($ast);

        // If this template needs function wrapping, do it after children are processed
        if ($shouldWrapInFunction) {
            $output = $this->componentCompiler->compile($output, $currentPath);
        }

        (new BladeService)->deleteTemporaryCacheDirectory();

        return $output;
    }

    public function render(array $nodes): string
    {
        return implode('', array_map(fn ($n) => $n->render(), $nodes));
    }

    public function isEnabled()
    {
        return $this->enabled;
    }

    public function isDisabled()
    {
        return ! $this->enabled;
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

    public function isDebugging()
    {
        return $this->debug;
    }

    public function tokenizer(): Tokenizer
    {
        return $this->tokenizer;
    }

    public function parser(): Parser
    {
        return $this->parser;
    }

    public function folder(): Folder
    {
        return $this->folder;
    }
}
