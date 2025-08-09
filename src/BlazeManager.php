<?php

namespace Livewire\Blaze;

use Livewire\Blaze\Events\ComponentFolded;
use Livewire\Blaze\Tokenizer\Tokenizer;
use Livewire\Blaze\Inspector\Inspector;
use Livewire\Blaze\Renderer\Renderer;
use Illuminate\Support\Facades\Event;
use Livewire\Blaze\Walker\Walker;
use Livewire\Blaze\Parser\Parser;
use Livewire\Blaze\Folder\Folder;

class BlazeManager
{
    protected $foldedEvents = [];

    protected $enabled = true;

    public function __construct(
        protected Tokenizer $tokenizer,
        protected Parser $parser,
        protected Renderer $renderer,
        protected Walker $walker,
        protected Inspector $inspector,
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

        $compiler = $view->getEngine()->getCompiler();
        $compiled = $compiler->getCompiledPath($path);
        $expired = $compiler->isExpired($path);

        if (! $expired) {
            $contents = file_get_contents($compiled);

            return (new FrontMatter)->sourceContainsExpiredFoldedDependencies($contents);
        }

        return false;
    }

    public function compile(string $template): string
    {
        $tokens = $this->tokenizer->tokenize($template);

        $ast = $this->parser->parse($tokens);

        $ast = $this->walker->walkPre($ast, function ($node) {
            return $this->inspector->inspect($node);
        });

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

    public function inspector(): Inspector
    {
        return $this->inspector;
    }

    public function folder(): Folder
    {
        return $this->folder;
    }
}
