<?php

namespace Livewire\Blaze;

use Livewire\Blaze\Events\ComponentFolded;
use Livewire\Blaze\Tokenizer\Tokenizer;
use Livewire\Blaze\Renderer\Renderer;
use Illuminate\Support\Facades\Event;
use Livewire\Blaze\Walker\Walker;
use Livewire\Blaze\Parser\Parser;
use Livewire\Blaze\Folder\Folder;

class BlazeManager
{
    protected $foldedEvents = [];

    protected $enabled = true;

    protected $expiredMemo = [];

    public function __construct(
        protected Tokenizer $tokenizer,
        protected Parser $parser,
        protected Renderer $renderer,
        protected Walker $walker,
        protected Folder $folder,
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
        $tokens = $this->tokenizer->tokenize($template);

        $ast = $this->parser->parse($tokens);

        $ast = $this->walker->walkPost($ast, function ($node) {
            return $this->folder->fold($node);
        });

        $output = $this->renderer->render($ast);

        return $output;
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

    public function tokenizer(): Tokenizer
    {
        return $this->tokenizer;
    }

    public function parser(): Parser
    {
        return $this->parser;
    }

    public function renderer(): Renderer
    {
        return $this->renderer;
    }

    public function folder(): Folder
    {
        return $this->folder;
    }
}
