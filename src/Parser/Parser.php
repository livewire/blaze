<?php

namespace Livewire\Blaze\Parser;

use Livewire\Blaze\Parser\Nodes\ComponentNode;
use Livewire\Blaze\Parser\Nodes\DirectiveNode;
use Livewire\Blaze\Parser\Nodes\EchoNode;
use Livewire\Blaze\Parser\Nodes\PhpBlockNode;
use Livewire\Blaze\Parser\Nodes\SlotNode;
use Livewire\Blaze\Parser\Nodes\TextNode;
use Livewire\Blaze\Parser\Nodes\VerbatimBlockNode;
use Livewire\Blaze\Parser\Tokens\TagCloseToken;
use Livewire\Blaze\Parser\Tokens\DirectiveToken;
use Livewire\Blaze\Parser\Tokens\EchoToken;
use Livewire\Blaze\Parser\Tokens\TagOpenToken;
use Livewire\Blaze\Parser\Tokens\PhpBlockToken;
use Livewire\Blaze\Parser\Tokens\TextToken;
use Livewire\Blaze\Parser\Tokens\VerbatimBlockToken;
use Livewire\Blaze\Support\AttributeParser;

/**
 * Converts a flat token stream into a nested AST of component, slot, and text nodes.
 */
class Parser
{
    public array $templates = [];

    public function __construct(
        protected Tokenizer $tokenizer,
        protected AttributeParser $attributes,
    ) {
    }

    /**
     * Parse tokens into an AST.
     */
    public function parse(string $content, ?string $path = null): Template
    {
        if ($path && isset($this->templates[$path])) {
            return $this->templates[$path];
        }

        $stack = new ParseStack;

        $tokens = $this->tokenizer->tokenize($content);

        foreach ($tokens as $token) {
            match(get_class($token)) {
                TagOpenToken::class => $this->handleOpeningTag($token, $stack),
                TagCloseToken::class => $this->handleClosingTag($token, $stack),
                DirectiveToken::class => $this->handleDirective($token, $stack),
                EchoToken::class => $this->handleEcho($token, $stack),
                TextToken::class => $this->handleText($token, $stack),
                PhpBlockToken::class => $this->handlePhpBlock($token, $stack),
                VerbatimBlockToken::class => $this->handleVerbatimBlock($token, $stack),
                default => throw new \RuntimeException('Unknown token type: ' . get_class($token))
            };
        }

        $template = new Template($stack->getAst());

        if ($path) {
            $this->templates[$path] = $template;
        }

        return $template;
    }

    /**
     * Handle an opening component tag token.
     */
    protected function handleOpeningTag(TagOpenToken $token, ParseStack $stack): void
    {
        if ($token->isSlot()) {
            $this->handleSlotOpen($token, $stack);

            return;
        }

        $node = new ComponentNode(
            name: $token->prefix === 'flux:' ? 'flux::' . $token->name : $token->name,
            prefix: $token->prefix,
            attributeString: trim($token->attributes),
            children: [],
            selfClosing: $token->selfClosing,
            attributes: $this->attributes->parse($token->attributes),
        );

        if ($token->selfClosing) {
            $stack->addToRoot($node);
        } else {
            $stack->pushContainer($node);
        }
    }

    /**
     * Handle a closing component or slot tag token.
     */
    protected function handleClosingTag(TagCloseToken $token, ParseStack $stack): void
    {
        $closed = $stack->popContainer();

        if ($closed instanceof SlotNode && $closed->slotStyle === 'short' && str_contains($token->name, ':')) {
            $closed->closeHasName = true;
        }
    }

    /**
     * Handle an opening slot tag token.
     */
    protected function handleSlotOpen(TagOpenToken $token, ParseStack $stack): void
    {
        $short = str_starts_with($token->name, 'slot:');

        $attributeString = $token->attributes;
        $attributes = $this->attributes->parse($token->attributes);

        $name = $short ? substr($token->name, strlen('slot:')) : ($attributes['name'] ?? 'slot');

        if (! $short && isset($attributes['name'])) {
            // TODO: We should be able to handle dynamic slot names...
            $name = $attributes['name']->value;
            $attributeString = preg_replace('/(?:^|\s+)name\s*=\s*(["\']).*?\1/', '', $token->attributes, 1);

            unset($attributes['name']);
        }

        $node = new SlotNode(
            name: $name,
            attributeString: trim($attributeString),
            slotStyle: $short ? 'short' : 'standard',
            children: [],
            prefix: $token->prefix . 'slot',
            closeHasName: false,
            attributes: $attributes,
        );

        $stack->pushContainer($node);
    }

    protected function handleDirective(DirectiveToken $token, ParseStack $stack): void
    {
        $node = new DirectiveNode(
            name: $token->name,
            original: $token->original,
            expression: $token->expression,
        );

        $stack->addToRoot($node);
    }

    protected function handleEcho(EchoToken $token, ParseStack $stack): void
    {
        $stack->addToRoot(new EchoNode(
            expression: $token->expression,
            original: $token->original,
        ));
    }

    /**
     * Handle a text content token.
     */
    protected function handleText(TextToken $token, ParseStack $stack): void
    {
        $node = new TextNode(content: $token->content);

        $stack->addToRoot($node);
    }

    /**
     * Handle a PHP block token.
     */
    protected function handlePhpBlock(PhpBlockToken $token, ParseStack $stack): void
    {
        $node = new PhpBlockNode(content: $token->content);

        $stack->addToRoot($node);
    }

    /**
     * Handle a verbatim block token.
     */
    protected function handleVerbatimBlock(VerbatimBlockToken $token, ParseStack $stack): void
    {
        $node = new VerbatimBlockNode(content: $token->content);

        $stack->addToRoot($node);
    }
}
