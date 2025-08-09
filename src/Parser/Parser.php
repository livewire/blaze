<?php

namespace Livewire\Blaze\Parser;

use Livewire\Blaze\Nodes\ComponentNode;
use Livewire\Blaze\Nodes\TextNode;
use Livewire\Blaze\Nodes\SlotNode;
use Livewire\Blaze\Tokenizer\Tokens\TagOpenToken;
use Livewire\Blaze\Tokenizer\Tokens\TagSelfCloseToken;
use Livewire\Blaze\Tokenizer\Tokens\TagCloseToken;
use Livewire\Blaze\Tokenizer\Tokens\SlotOpenToken;
use Livewire\Blaze\Tokenizer\Tokens\SlotCloseToken;
use Livewire\Blaze\Tokenizer\Tokens\TextToken;

class Parser
{
    public function parse(array $tokens): array
    {
        $stack = new ParseStack();

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

    protected function handleTagOpen(TagOpenToken $token, ParseStack $stack): void
    {
        $node = new ComponentNode(
            name: $token->namespace . $token->name,
            prefix: $token->prefix,
            attributes: $token->attributes,
            children: [],
            selfClosing: false
        );

        $stack->pushContainer($node);
    }

    protected function handleTagSelfClose(TagSelfCloseToken $token, ParseStack $stack): void
    {
        $node = new ComponentNode(
            name: $token->namespace . $token->name,
            prefix: $token->prefix,
            attributes: $token->attributes,
            children: [],
            selfClosing: true
        );

        $stack->addToRoot($node);
    }

    protected function handleTagClose(TagCloseToken $token, ParseStack $stack): void
    {
        $stack->popContainer();
    }

    protected function handleSlotOpen(SlotOpenToken $token, ParseStack $stack): void
    {
        $node = new SlotNode(
            name: $token->name ?? '',
            attributes: $token->attributes,
            slotStyle: $token->slotStyle,
            children: [],
            prefix: $token->prefix,
            closeHasName: false,
        );

        $stack->pushContainer($node);
    }

    protected function handleSlotClose(SlotCloseToken $token, ParseStack $stack): void
    {
        $closed = $stack->popContainer();
        if ($closed instanceof SlotNode && $closed->slotStyle === 'short') {
            // If tokenizer captured a :name on the close tag, mark it
            if (!empty($token->name)) {
                $closed->closeHasName = true;
            }
        }
    }

    protected function handleText(TextToken $token, ParseStack $stack): void
    {
        // Always preserve text content, including whitespace
        $node = new TextNode(content: $token->content);
        $stack->addToRoot($node);
    }
}