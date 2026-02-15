<?php

namespace Livewire\Blaze\Parser;

use Livewire\Blaze\Parser\Tokens\TagSelfCloseToken;
use Livewire\Blaze\Parser\Tokens\SlotCloseToken;
use Livewire\Blaze\Parser\Tokens\SlotOpenToken;
use Livewire\Blaze\Parser\Tokens\TagCloseToken;
use Livewire\Blaze\Parser\Tokens\TagOpenToken;
use Livewire\Blaze\Parser\Tokens\TextToken;
use Livewire\Blaze\Parser\Tokens\Token;

/**
 * Finite state machine that lexes Blade templates into component/slot/text tokens.
 */
class Tokenizer
{
    protected array $prefixes = [
        'flux:' => [
            'namespace' => 'flux::',
            'slot' => 'x-slot',
        ],
        'x:' => [
            'namespace' => '',
            'slot' => 'x-slot',
        ],
        'x-' => [
            'namespace' => '',
            'slot' => 'x-slot',
        ],
    ];

    protected string $content = '';

    protected int $position = 0;

    protected int $length = 0;

    protected array $tokens = [];

    protected string $buffer = '';

    protected ?Token $currentToken = null;

    protected array $tagStack = [];

    protected string $currentPrefix = '';

    protected string $currentSlotPrefix = '';

    /**
     * Tokenize a Blade template into an array of tokens.
     */
    public function tokenize(string $content): array
    {
        $this->resetTokenizer($content);

        $state = TokenizerState::TEXT;

        while (!$this->isAtEnd()) {
            $state = match($state) {
                TokenizerState::TEXT => $this->handleTextState(),
                TokenizerState::TAG_OPEN => $this->handleTagOpenState(),
                TokenizerState::TAG_CLOSE => $this->handleTagCloseState(),
                TokenizerState::ATTRIBUTE_NAME => $this->handleAttributeState(),
                TokenizerState::SLOT => $this->handleSlotState(),
                TokenizerState::SLOT_CLOSE => $this->handleSlotCloseState(),
                TokenizerState::SHORT_SLOT => $this->handleShortSlotState(),
                default => throw new \RuntimeException("Unknown state: $state"),
            };
        }

        $this->flushBuffer();

        return $this->tokens;
    }

    /**
     * Reset all tokenizer state for a new tokenization pass.
     */
    protected function resetTokenizer(string $content): void
    {
        $this->content = $content;
        $this->position = 0;
        $this->length = strlen($content);
        $this->tokens = [];
        $this->buffer = '';
        $this->currentToken = null;
        $this->tagStack = [];
        $this->currentPrefix = '';
        $this->currentSlotPrefix = '';
    }

    /**
     * Process text state, detecting component/slot tag boundaries.
     */
    protected function handleTextState(): TokenizerState
    {
        $char = $this->current();

        if ($char === '<') {
            if ($slotInfo = $this->matchSlotOpen()) {
                $this->flushBuffer();

                $this->currentSlotPrefix = $slotInfo['prefix'];

                if ($slotInfo['isShort']) {
                    $this->currentToken = new SlotOpenToken(slotStyle: 'short', prefix: $slotInfo['prefix']);

                    $this->advance(strlen('<' . $slotInfo['prefix'] . ':'));

                    return TokenizerState::SHORT_SLOT;
                } else {
                    $this->currentToken = new SlotOpenToken(slotStyle: 'standard', prefix: $slotInfo['prefix']);

                    $this->advance(strlen('<' . $slotInfo['prefix']));

                    return TokenizerState::SLOT;
                }
            }

            if ($slotInfo = $this->matchSlotClose()) {
                $this->flushBuffer();

                $this->currentToken = new SlotCloseToken();

                $this->currentSlotPrefix = $slotInfo['prefix'];

                $this->advance(strlen('</' . $slotInfo['prefix']));

                return TokenizerState::SLOT_CLOSE;
            }

            if ($prefixInfo = $this->matchComponentOpen()) {
                $this->flushBuffer();

                $this->currentPrefix = $prefixInfo['prefix'];

                $this->currentToken = new TagOpenToken(
                    name: '',
                    prefix: $prefixInfo['prefix'],
                    namespace: $prefixInfo['namespace']
                );

                $this->advance(strlen('<' . $prefixInfo['prefix']));

                return TokenizerState::TAG_OPEN;
            }

            if ($this->peek(1) === '/' && ($prefixInfo = $this->matchComponentClose())) {
                $this->flushBuffer();

                $this->currentPrefix = $prefixInfo['prefix'];

                $this->currentToken = new TagCloseToken(
                    name: '',
                    prefix: $prefixInfo['prefix'],
                    namespace: $prefixInfo['namespace']
                );

                $this->advance(strlen('</' . $prefixInfo['prefix']));

                return TokenizerState::TAG_CLOSE;
            }
        }

        $this->buffer .= $char;

        $this->advance();

        return TokenizerState::TEXT;
    }

    /**
     * Process tag open state, extracting the component name.
     */
    protected function handleTagOpenState(): TokenizerState
    {
        if ($name = $this->matchTagName()) {
            $this->currentToken->name = $name;

            $this->tagStack[] = $name;

            $this->advance(strlen($name));

            return TokenizerState::ATTRIBUTE_NAME;
        }

        $this->advance();

        return TokenizerState::TAG_OPEN;
    }

    /**
     * Process closing tag state, extracting the component name.
     */
    protected function handleTagCloseState(): TokenizerState
    {
        if ($name = $this->matchTagName()) {
            $this->currentToken->name = $name;

            array_pop($this->tagStack);

            $this->advance(strlen($name));

            if ($this->current() === '>') {
                $this->tokens[] = $this->currentToken;

                $this->advance();

                return TokenizerState::TEXT;
            }
        }

        $this->advance();

        return TokenizerState::TAG_CLOSE;
    }

    /**
     * Process attribute collection state, handling self-closing detection.
     */
    protected function handleAttributeState(): TokenizerState
    {
        $char = $this->current();

        if ($char === ' ') {
            $this->advance();

            return TokenizerState::ATTRIBUTE_NAME;
        }

        if ($char === '>') {
            $this->tokens[] = $this->currentToken;

            $this->advance();

            return TokenizerState::TEXT;
        }

        if ($char === '/' && $this->peek() === '>') {
            $this->currentToken = new TagSelfCloseToken(
                name: $this->currentToken->name,
                prefix: $this->currentToken->prefix,
                namespace: $this->currentToken->namespace,
                attributes: $this->currentToken->attributes
            );

            array_pop($this->tagStack);

            $this->tokens[] = $this->currentToken;

            $this->advance(2);

            return TokenizerState::TEXT;
        }

        $attributes = $this->collectAttributes();

        if ($attributes !== null) {
            $this->currentToken->attributes = $attributes;
        }

        return TokenizerState::ATTRIBUTE_NAME;
    }

    /**
     * Process standard slot tag state.
     */
    protected function handleSlotState(): TokenizerState
    {
        $char = $this->current();

        if ($char === ' ') {
            $this->advance();

            return TokenizerState::SLOT;
        }

        if ($this->match('/^name="([^"]+)"/')) {
            $matches = [];

            preg_match('/^name="([^"]+)"/', $this->remaining(), $matches);

            $this->currentToken->name = $matches[1];

            $this->advance(strlen($matches[0]));

            return TokenizerState::ATTRIBUTE_NAME;
        }

        if ($char === '>') {
            $this->tokens[] = $this->currentToken;

            $this->advance();

            return TokenizerState::TEXT;
        }

        $this->advance();

        return TokenizerState::SLOT;
    }

    /**
     * Process closing slot tag state.
     */
    protected function handleSlotCloseState(): TokenizerState
    {
        if ($this->match('/^:[a-zA-Z0-9-]+/')) {
            $matches = [];

            preg_match('/^:[a-zA-Z0-9-]+/', $this->remaining(), $matches);

            $this->currentToken->name = substr($matches[0], 1);

            $this->advance(strlen($matches[0]));
        }

        if ($this->current() === '>') {
            $this->tokens[] = $this->currentToken;

            $this->advance();

            return TokenizerState::TEXT;
        }

        $this->advance();

        return TokenizerState::SLOT_CLOSE;
    }

    /**
     * Process short slot syntax state (<x-slot:name>).
     */
    protected function handleShortSlotState(): TokenizerState
    {
        if ($name = $this->matchSlotName()) {
            $this->currentToken->name = $name;

            $this->advance(strlen($name));

            $attrBuffer = '';
            while (! $this->isAtEnd() && $this->current() !== '>') {
                $attrBuffer .= $this->current();

                $this->advance();
            }

            if (trim($attrBuffer) !== '') {
                $this->currentToken->attributes = trim($attrBuffer);
            }

            if ($this->current() === '>') {
                $this->tokens[] = $this->currentToken;

                $this->advance();

                return TokenizerState::TEXT;
            }
        }

        $this->advance();

        return TokenizerState::SHORT_SLOT;
    }

    /**
     * Collect the full attribute string, respecting nested quotes and brackets.
     */
    protected function collectAttributes(): ?string
    {
        $attrString = '';
        $inSingleQuote = false;
        $inDoubleQuote = false;
        $braceCount = 0;
        $bracketCount = 0;
        $parenCount = 0;

        while (!$this->isAtEnd()) {
            $char = $this->current();

            $prevChar = $this->position > 0 ? $this->content[$this->position - 1] : '';

            if ($char === '"' && !$inSingleQuote && $prevChar !== '\\') {
                $inDoubleQuote = !$inDoubleQuote;
            } elseif ($char === "'" && !$inDoubleQuote && $prevChar !== '\\') {
                $inSingleQuote = !$inSingleQuote;
            }

            if (!$inSingleQuote && !$inDoubleQuote) {
                match($char) {
                    '{' => $braceCount++,
                    '}' => $braceCount--,
                    '[' => $bracketCount++,
                    ']' => $bracketCount--,
                    '(' => $parenCount++,
                    ')' => $parenCount--,
                    default => null
                };
            }

            if (
                ($char === '>' || ($char === '/' && $this->peek() === '>')) &&
                $braceCount === 0 && $bracketCount === 0 && $parenCount === 0 &&
                !$inSingleQuote && !$inDoubleQuote
            ) {
                break;
            }

            $attrString .= $char;

            $this->advance();
        }

        return trim($attrString) !== '' ? trim($attrString) : null;
    }

    /**
     * Try to match a slot opening tag at the current position.
     */
    protected function matchSlotOpen(): ?array
    {
        foreach ($this->prefixes as $prefix => $config) {
            $slotPrefix = $config['slot'];

            if ($this->matchesAt('<' . $slotPrefix . ':')) {
                return ['prefix' => $slotPrefix, 'isShort' => true];
            }

            if ($this->matchesAt('<' . $slotPrefix)) {
                $nextChar = $this->peek(strlen('<' . $slotPrefix));

                if ($nextChar !== ':') {
                    return ['prefix' => $slotPrefix, 'isShort' => false];
                }
            }
        }

        return null;
    }

    /**
     * Try to match a slot closing tag at the current position.
     */
    protected function matchSlotClose(): ?array
    {
        foreach ($this->prefixes as $prefix => $config) {
            $slotPrefix = $config['slot'];

            if ($this->matchesAt('</' . $slotPrefix)) {
                return ['prefix' => $slotPrefix];
            }
        }

        return null;
    }

    /**
     * Try to match a component opening tag at the current position.
     */
    protected function matchComponentOpen(): ?array
    {
        foreach ($this->prefixes as $prefix => $config) {
            if ($this->matchesAt('<' . $prefix)) {
                return [
                    'prefix' => $prefix,
                    'namespace' => $config['namespace'] ?? ''
                ];
            }
        }

        return null;
    }

    /**
     * Try to match a component closing tag at the current position.
     */
    protected function matchComponentClose(): ?array
    {
        foreach ($this->prefixes as $prefix => $config) {
            if ($this->matchesAt('</' . $prefix)) {
                return [
                    'prefix' => $prefix,
                    'namespace' => $config['namespace'] ?? ''
                ];
            }
        }

        return null;
    }

    /**
     * Match a tag name (alphanumeric, hyphens, dots, colons) at the current position.
     */
    protected function matchTagName(): ?string
    {
        if (preg_match('/^[a-zA-Z0-9-\.:]+/', $this->remaining(), $matches)) {
            return $matches[0];
        }

        return null;
    }

    /**
     * Match a slot name (alphanumeric, hyphens) at the current position.
     */
    protected function matchSlotName(): ?string
    {
        if (preg_match('/^[a-zA-Z0-9-]+/', $this->remaining(), $matches)) {
            return $matches[0];
        }

        return null;
    }

    /**
     * Test a regex pattern against the remaining content.
     */
    protected function match(string $pattern): bool
    {
        return preg_match($pattern, $this->remaining()) === 1;
    }

    /**
     * Check if a literal string matches at the current position.
     */
    protected function matchesAt(string $string): bool
    {
        return substr($this->content, $this->position, strlen($string)) === $string;
    }

    /**
     * Get the character at the current position.
     */
    protected function current(): string
    {
        return $this->isAtEnd() ? '' : $this->content[$this->position];
    }

    /**
     * Peek at a character at an offset from the current position.
     */
    protected function peek(int $offset = 1): string
    {
        $pos = $this->position + $offset;

        return $pos >= $this->length ? '' : $this->content[$pos];
    }

    /**
     * Get the remaining content from the current position.
     */
    protected function remaining(): string
    {
        return substr($this->content, $this->position);
    }

    /**
     * Advance the position by a number of characters.
     */
    protected function advance(int $count = 1): void
    {
        $this->position += $count;
    }

    /**
     * Check if the tokenizer has reached the end of input.
     */
    protected function isAtEnd(): bool
    {
        return $this->position >= $this->length;
    }

    /**
     * Emit any accumulated text buffer as a TextToken.
     */
    protected function flushBuffer(): void
    {
        if ($this->buffer !== '') {
            $this->tokens[] = new TextToken($this->buffer);

            $this->buffer = '';
        }
    }
}