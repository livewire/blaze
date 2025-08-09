<?php

namespace Livewire\Blaze\Tokenizer;

use Livewire\Blaze\Tokenizer\Tokens\TagSelfCloseToken;
use Livewire\Blaze\Tokenizer\Tokens\SlotCloseToken;
use Livewire\Blaze\Tokenizer\Tokens\SlotOpenToken;
use Livewire\Blaze\Tokenizer\Tokens\TagCloseToken;
use Livewire\Blaze\Tokenizer\Tokens\TagOpenToken;
use Livewire\Blaze\Tokenizer\Tokens\TextToken;
use Livewire\Blaze\Tokenizer\Tokens\Token;

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

    public function tokenize(string $content): array
    {
        $this->resetTokenizer($content);

        $state = State::TEXT;

        while (!$this->isAtEnd()) {
            $state = match($state) {
                State::TEXT => $this->handleTextState(),
                State::TAG_OPEN => $this->handleTagOpenState(),
                State::TAG_CLOSE => $this->handleTagCloseState(),
                State::ATTRIBUTE_NAME => $this->handleAttributeState(),
                State::SLOT => $this->handleSlotState(),
                State::SLOT_CLOSE => $this->handleSlotCloseState(),
                State::SHORT_SLOT => $this->handleShortSlotState(),
                default => throw new \RuntimeException("Unknown state: $state"),
            };
        }

        $this->flushBuffer();

        return $this->tokens;
    }

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

    protected function handleTextState(): State
    {
        $char = $this->current();

        // Check for slot tags first...
        if ($char === '<') {
            if ($slotInfo = $this->matchSlotOpen()) {
                $this->flushBuffer();

                $this->currentSlotPrefix = $slotInfo['prefix'];

                if ($slotInfo['isShort']) {
                    $this->currentToken = new SlotOpenToken(slotStyle: 'short', prefix: $slotInfo['prefix']);

                    $this->advance(strlen('<' . $slotInfo['prefix'] . ':'));

                    return State::SHORT_SLOT;
                } else {
                    $this->currentToken = new SlotOpenToken(slotStyle: 'standard', prefix: $slotInfo['prefix']);

                    $this->advance(strlen('<' . $slotInfo['prefix']));

                    return State::SLOT;
                }
            }

            if ($slotInfo = $this->matchSlotClose()) {
                $this->flushBuffer();

                $this->currentToken = new SlotCloseToken();

                $this->currentSlotPrefix = $slotInfo['prefix'];

                $this->advance(strlen('</' . $slotInfo['prefix']));

                return State::SLOT_CLOSE;
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

                return State::TAG_OPEN;
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

                return State::TAG_CLOSE;
            }
        }

        // Regular text...
        $this->buffer .= $char;

        $this->advance();

        return State::TEXT;
    }

    protected function handleTagOpenState(): State
    {
        if ($name = $this->matchTagName()) {
            $this->currentToken->name = $name;

            $this->tagStack[] = $name;

            $this->advance(strlen($name));

            return State::ATTRIBUTE_NAME;
        }

        $this->advance();

        return State::TAG_OPEN;
    }

    protected function handleTagCloseState(): State
    {
        if ($name = $this->matchTagName()) {
            $this->currentToken->name = $name;

            array_pop($this->tagStack);

            $this->advance(strlen($name));

            if ($this->current() === '>') {
                $this->tokens[] = $this->currentToken;

                $this->advance();

                return State::TEXT;
            }
        }

        $this->advance();

        return State::TAG_CLOSE;
    }

    protected function handleAttributeState(): State
    {
        $char = $this->current();

        // Skip whitespace...
        if ($char === ' ') {
            $this->advance();

            return State::ATTRIBUTE_NAME;
        }

        // End of tag...
        if ($char === '>') {
            $this->tokens[] = $this->currentToken;

            $this->advance();

            return State::TEXT;
        }

        // Self-closing tag...
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

            return State::TEXT;
        }

        $attributes = $this->collectAttributes();

        if ($attributes !== null) {
            $this->currentToken->attributes = $attributes;
        }

        return State::ATTRIBUTE_NAME;
    }

    protected function handleSlotState(): State
    {
        $char = $this->current();

        if ($char === ' ') {
            $this->advance();

            return State::SLOT;
        }

        // Check for name attribute
        if ($this->match('/^name="([^"]+)"/')) {
            $matches = [];

            preg_match('/^name="([^"]+)"/', $this->remaining(), $matches);

            $this->currentToken->name = $matches[1];

            $this->advance(strlen($matches[0]));

            return State::ATTRIBUTE_NAME;
        }

        if ($char === '>') {
            $this->tokens[] = $this->currentToken;

            $this->advance();

            return State::TEXT;
        }

        $this->advance();

        return State::SLOT;
    }

    protected function handleSlotCloseState(): State
    {
        // Check for slot:name pattern...
        if ($this->match('/^:[a-zA-Z0-9-]+/')) {
            $matches = [];

            preg_match('/^:[a-zA-Z0-9-]+/', $this->remaining(), $matches);

            $this->currentToken->name = substr($matches[0], 1);

            $this->advance(strlen($matches[0]));
        }

        if ($this->current() === '>') {
            $this->tokens[] = $this->currentToken;

            $this->advance();

            return State::TEXT;
        }

        $this->advance();

        return State::SLOT_CLOSE;
    }

    protected function handleShortSlotState(): State
    {
        if ($name = $this->matchSlotName()) {
            $this->currentToken->name = $name;

            $this->advance(strlen($name));

            // Collect attributes until >...
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

                return State::TEXT;
            }
        }

        $this->advance();

        return State::SHORT_SLOT;
    }

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

            // Track quote state...
            if ($char === '"' && !$inSingleQuote && $prevChar !== '\\') {
                $inDoubleQuote = !$inDoubleQuote;
            } elseif ($char === "'" && !$inDoubleQuote && $prevChar !== '\\') {
                $inSingleQuote = !$inSingleQuote;
            }

            // Track nesting only outside quotes...
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

            // Check for end of attributes...
            if (($char === '>' || ($char === '/' && $this->peek() === '>')) &&
                !$inSingleQuote && !$inDoubleQuote &&

                $braceCount === 0 && $bracketCount === 0 && $parenCount === 0) {

                break;
            }

            $attrString .= $char;

            $this->advance();
        }

        return trim($attrString) !== '' ? trim($attrString) : null;
    }

    protected function matchSlotOpen(): ?array
    {
        foreach ($this->prefixes as $prefix => $config) {
            $slotPrefix = $config['slot'];

            // Check for short slot syntax...
            if ($this->matchesAt('<' . $slotPrefix . ':')) {
                return ['prefix' => $slotPrefix, 'isShort' => true];
            }

            // Check for standard slot syntax...
            if ($this->matchesAt('<' . $slotPrefix)) {
                $nextChar = $this->peek(strlen('<' . $slotPrefix));

                if ($nextChar !== ':') {
                    return ['prefix' => $slotPrefix, 'isShort' => false];
                }
            }
        }

        return null;
    }

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

    protected function matchTagName(): ?string
    {
        if (preg_match('/^[a-zA-Z0-9-\.:]+/', $this->remaining(), $matches)) {
            return $matches[0];
        }

        return null;
    }

    protected function matchSlotName(): ?string
    {
        if (preg_match('/^[a-zA-Z0-9-]+/', $this->remaining(), $matches)) {
            return $matches[0];
        }

        return null;
    }

    protected function match(string $pattern): bool
    {
        return preg_match($pattern, $this->remaining()) === 1;
    }

    protected function matchesAt(string $string): bool
    {
        return substr($this->content, $this->position, strlen($string)) === $string;
    }

    protected function current(): string
    {
        return $this->isAtEnd() ? '' : $this->content[$this->position];
    }

    protected function peek(int $offset = 1): string
    {
        $pos = $this->position + $offset;

        return $pos >= $this->length ? '' : $this->content[$pos];
    }

    protected function remaining(): string
    {
        return substr($this->content, $this->position);
    }

    protected function advance(int $count = 1): void
    {
        $this->position += $count;
    }

    protected function isAtEnd(): bool
    {
        return $this->position >= $this->length;
    }

    protected function flushBuffer(): void
    {
        if ($this->buffer !== '') {
            $this->tokens[] = new TextToken($this->buffer);

            $this->buffer = '';
        }
    }
}