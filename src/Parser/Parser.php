<?php

namespace Livewire\Blaze\Parser;

use Livewire\Blaze\BladeService;
use Livewire\Blaze\Parser\Nodes\ComponentNode;
use Livewire\Blaze\Parser\Nodes\SlotNode;
use Livewire\Blaze\Parser\Nodes\TextNode;
use Livewire\Blaze\Parser\Tokenizer;
use Livewire\Blaze\Parser\Tokens\SlotCloseToken;
use Livewire\Blaze\Parser\Tokens\SlotOpenToken;
use Livewire\Blaze\Parser\Tokens\TagCloseToken;
use Livewire\Blaze\Parser\Tokens\TagOpenToken;
use Livewire\Blaze\Parser\Tokens\TagSelfCloseToken;
use Livewire\Blaze\Parser\Tokens\TextToken;
use Livewire\Blaze\Support\AttributeParser;

/**
 * Converts a flat token stream into a nested AST of component, slot, and text nodes.
 */
class Parser
{
    public function __construct(
        protected Tokenizer $tokenizer,
    ) {
    }

    /**
     * Parse tokens into an AST.
     */
    public function parse(string $content): array
    {
        $stack = new ParseStack;

        $tokens = $this->tokenizer->tokenize($content);

        foreach ($tokens as $token) {
            match(get_class($token)) {
                TagOpenToken::class => $this->handleTagOpen($token, $stack),
                TagSelfCloseToken::class => $this->handleTagSelfClose($token, $stack),
                TagCloseToken::class => $this->handleTagClose($token, $stack),
                SlotOpenToken::class => $this->handleSlotOpen($token, $stack),
                SlotCloseToken::class => $this->handleSlotClose($token, $stack),
                TextToken::class => $this->handleText($token, $stack),
                default => throw new \RuntimeException('Unknown token type: ' . get_class($token))
            };
        }

        return $stack->getAst();
    }

    /**
     * Handle an opening component tag token.
     */
    protected function handleTagOpen(TagOpenToken $token, ParseStack $stack): void
    {
        $attributeString = implode(' ', $token->attributes);

        $node = new ComponentNode(
            name: $token->namespace . $token->name,
            prefix: $token->prefix,
            attributeString: $attributeString,
            children: [],
            selfClosing: false,
            attributes: AttributeParser::parseAttributeStringToArray(
                BladeService::preprocessAttributeString($attributeString)
            ),
        );

        $stack->pushContainer($node);
    }

    /**
     * Handle a self-closing component tag token.
     */
    protected function handleTagSelfClose(TagSelfCloseToken $token, ParseStack $stack): void
    {
        $attributeString = implode(' ', $token->attributes);

        $node = new ComponentNode(
            name: $token->namespace . $token->name,
            prefix: $token->prefix,
            attributeString: $attributeString,
            children: [],
            selfClosing: true,
            attributes: AttributeParser::parseAttributeStringToArray(
                BladeService::preprocessAttributeString($attributeString)
            ),
        );

        $stack->addToRoot($node);
    }

    /**
     * Handle a closing component tag token.
     */
    protected function handleTagClose(TagCloseToken $token, ParseStack $stack): void
    {
        $stack->popContainer();
    }

    /**
     * Handle an opening slot tag token.
     */
    protected function handleSlotOpen(SlotOpenToken $token, ParseStack $stack): void
    {
        $attributeString = implode(' ', $token->attributes);

        $node = new SlotNode(
            name: $token->name ?? 'slot',
            attributeString: $attributeString,
            slotStyle: $token->slotStyle,
            children: [],
            prefix: $token->prefix,
            closeHasName: false,
            attributes: AttributeParser::parseAttributeStringToArray(
                BladeService::preprocessAttributeString($attributeString)
            ),
        );

        $stack->pushContainer($node);
    }

    /**
     * Handle a closing slot tag token.
     */
    protected function handleSlotClose(SlotCloseToken $token, ParseStack $stack): void
    {
        $closed = $stack->popContainer();
        if ($closed instanceof SlotNode && $closed->slotStyle === 'short') {
            // If tokenizer captured a :name on the close tag, mark it
            if (! empty($token->name)) {
                $closed->closeHasName = true;
            }
        }
    }

    /**
     * Handle a text content token.
     */
    protected function handleText(TextToken $token, ParseStack $stack): void
    {
        $node = new TextNode(content: $token->content);

        $stack->addToRoot($node);
    }
}