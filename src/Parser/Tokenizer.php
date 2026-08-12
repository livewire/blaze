<?php

namespace Livewire\Blaze\Parser;

use Illuminate\Support\Str;
use Livewire\Blaze\BladeService;
use Livewire\Blaze\Parser\Tokens\TagCloseToken;
use Livewire\Blaze\Parser\Tokens\DirectiveToken;
use Livewire\Blaze\Parser\Tokens\EchoToken;
use Livewire\Blaze\Parser\Tokens\TagOpenToken;
use Livewire\Blaze\Parser\Tokens\PhpBlockToken;
use Livewire\Blaze\Parser\Tokens\TextToken;
use Livewire\Blaze\Parser\Tokens\Token;
use Livewire\Blaze\Parser\Tokens\VerbatimBlockToken;
use Livewire\Blaze\Support\LaravelRegex;

/**
 * Lexes Blade templates into tags, directives, PHP blocks, verbatim blocks, and text tokens.
 */
class Tokenizer
{
    public function __construct(
        protected BladeService $blade,
    ) {
    }

    protected array $tokens = [];
    protected string $buffer = '';
    protected string $content;
    protected int $position;
    protected int $length;

    /**
     * Tokenize a Blade template into an array of tokens.
     */
    public function tokenize(string $template): array
    {
        $this->tokens = [];
        $this->buffer = '';

        foreach (token_get_all($template) as $token) {
            if (is_array($token) && $token[0] === T_INLINE_HTML) {
                $this->flushBuffer(PhpBlockToken::class);
                $this->tokenizeString($token[1]);

                continue;
            }

            $this->buffer .= is_array($token) ? $token[1] : $token;
        }

        $this->flushBuffer(PhpBlockToken::class);

        return $this->tokens;
    }

    /**
     * Tokenize a string of inline HTML content.
     */
    protected function tokenizeString(string $content): void
    {
        $this->buffer = '';
        $this->position = 0;
        $this->content = $this->blade->compileComments($content);
        $this->length = strlen($this->content);

        while (! $this->isAtEnd()) {
            $this->process();
        }

        $this->flushBuffer();
    }

    /**
     * Process the token starting at the current position.
     */
    protected function process(): void
    {
        if ($this->startsWith('@php') && ($match = $this->matchDirective()) && ! $match['expression']) {
            $offset = $this->position;

            $this->flushBuffer();

            $this->advance(strlen('@php'));

            if ($this->advanceUntil('@endphp', fn () => $this->matchDirective())) {
                $this->advance(strlen('@endphp'));

                $this->flushBuffer(PhpBlockToken::class);
            } else {
                $original = rtrim($match['original']);

                $this->emitToken(new DirectiveToken($match['name'], $original));

                $this->rewind($offset + strlen($original));
            }

            return;
        }

        if ($this->startsWith('@verbatim') && ($match = $this->matchDirective()) && ! $match['expression']) {
            $offset = $this->position;

            $this->flushBuffer();

            $this->advance(strlen('@verbatim'));

            if ($this->advanceUntil('@endverbatim', fn () => $this->matchDirective())) {
                $this->advance(strlen('@endverbatim'));

                $this->flushBuffer(VerbatimBlockToken::class);
            } else {
                $this->emitToken(new DirectiveToken($match['name'], $match['original']));

                $this->rewind($offset + strlen($match['original']));
            }

            return;
        }

        if ($this->current() === '{' && $match = $this->matchEcho()) {
            if ($this->position > 0 && $this->content[$this->position - 1] === '@') {
                $this->advance(strlen($match['original']));

                return;
            }

            $this->flushBuffer();
            $this->advance(strlen($match['original']));
            $this->emitToken(new EchoToken($match['expression'], $match['original']));

            return;
        }

        if ($this->current() === '@' && $match = $this->matchDirective()) {
            $this->flushBuffer();

            $this->advance(strlen($match['original']));

            $this->emitToken(new DirectiveToken(
                name: $match['name'],
                original: $match['original'],
                expression: $match['expression'],
            ));

            return;
        }

        if ($this->current() === '<' && $match = $this->matchOpeningTag()) {
            $this->flushBuffer();

            $this->advance(strlen($match['original']));

            $this->emitToken(new TagOpenToken($match['prefix'], $match['name'], $match['attributes'], $match['original'], $match['selfClosing']));

            return;
        }

        if ($this->current() === '<' && $this->peek() === '/' && $match = $this->matchClosingTag()) {
            $this->flushBuffer();

            $this->advance(strlen($match['original']));

            $this->emitToken(new TagCloseToken($match['prefix'], $match['name'], $match['original']));

            return;
        }

        $this->advanceUntilNext('<@{');
    }

    /**
     * Match an executable Blade echo at the current position.
     */
    protected function matchEcho(): ?array
    {
        $remaining = $this->remaining();

        foreach (['/^{!!\s*(.+?)\s*!!}/s', '/^{{{\s*(.+?)\s*}}}/s', '/^{{\s*(.+?)\s*}}/s'] as $pattern) {
            if (! preg_match($pattern, $remaining, $matches)) {
                continue;
            }

            return [
                'expression' => $matches[1],
                'original' => $matches[0],
            ];
        }

        return null;
    }

    /**
     * Match a Blade directive at the current position.
     */
    protected function matchDirective(): ?array
    {
        // Skip escaped directives like `@@if`
        if ($this->peek(1) === '@') {
            $this->advance(2);

            return null;
        }

        // Skip @ preceded by a word char like `info@example`
        if ($this->position > 0 && preg_match('/\w/', $this->content[$this->position - 1])) {
            return null;
        }

        /**
         * The following code matches the parenthesis handling in Blade as closely as possible.
         *
         * @see \Illuminate\View\Compilers\BladeCompiler::compileStatements()
         */

        $template = $this->remaining();

        if (! preg_match(LaravelRegex::BLADE_STATEMENT, $template, $matches, PREG_UNMATCHED_AS_NULL)) {
            return null;
        }

        $match = [
            $matches[0],
            $matches[1],
            $matches[2],
            $matches[3] ?: null,
            $matches[4] ?: null,
        ];

        // Here we check to see if we have properly found the closing parenthesis by
        // regex pattern or not, and will recursively continue on to the next ")"
        // then check again until the tokenizer confirms we find the right one.
        while (isset($match[4]) &&
               Str::endsWith($match[0], ')') &&
               ! $this->blade->hasEvenNumberOfParentheses($match[0])) {
            if (($after = Str::after($template, $match[0])) === $template) {
                break;
            }

            $rest = Str::before($after, ')');

            $match[0] = $match[0].$rest.')';
            $match[3] = $match[3].$rest.')';
            $match[4] = $match[4].$rest;
        }

        // Reject matches that do not begin at the current position.
        if (! Str::startsWith($template, $match[0])) {
            return null;
        }

        return [
            'name' => $match[1],
            'original' => $match[0],
            'expression' => isset($match[3]) ? (Str::substr($match[3], 1, -1) ?: null) : null,
        ];
    }

    /**
     * Match an opening or self-closing component tag at the current position.
     */
    protected function matchOpeningTag(): array|null
    {
        $pattern = "/^<\s*(x[-:]|flux:)([\w\-:.]*)". LaravelRegex::ATTRIBUTES ."(?<![=\-])(?<selfClosing>\/?)>/x";

        preg_match($pattern, $this->remaining(), $matches);

        if ($matches) {
            return [
                'original' => $matches[0],
                'prefix' => $matches[1],
                'name' => $matches[2],
                'attributes' => ltrim($matches['attributes']),
                'selfClosing' => $matches['selfClosing'] === '/',
            ];
        }

        return null;
    }

    /**
     * Match a closing component tag at the current position.
     */
    protected function matchClosingTag(): array|null
    {
        $pattern = "/^<\/\s*(x[-:]|flux:)([\w\-\:\.]*)\s*>/x";

        preg_match($pattern, $this->remaining(), $matches);

        if ($matches) {
            return [
                'original' => $matches[0],
                'prefix' => $matches[1],
                'name' => $matches[2],
            ];
        }

        return null;
    }

    /**
     * Get the character at the current position.
     */
    protected function current(): string
    {
        return $this->isAtEnd() ? '' : $this->content[$this->position];
    }

    /**
     * Get the remaining content from the current position.
     */
    protected function remaining(): string
    {
        return substr($this->content, $this->position);
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
     * Advance the position by a number of characters.
     */
    protected function advance(int $count = 1): void
    {
        $this->buffer .= substr($this->content, $this->position, $count);

        $this->position += $count;
    }

    /**
     * Advance until a matching string satisfying the optional condition is found.
     */
    protected function advanceUntil(string $str, ?callable $condition = null): bool
    {
        while (! $this->isAtEnd()) {
            $this->advanceUntilNext($str[0]);

            if ($this->startsWith($str) && (is_null($condition) || $condition())) {
                return true;
            }
        }

        return false;
    }

    /**
     * Advance through the next occurrence of any of the given characters.
     */
    protected function advanceUntilNext(string $characters): void
    {
        $this->advance(strcspn($this->content, $characters, $this->position + 1) + 1);
    }

    /**
     * Determine whether the remaining content starts with the given string.
     */
    protected function startsWith(string $str): bool
    {
        return substr_compare($this->content, $str, $this->position, strlen($str)) === 0;
    }

    /**
     * Check if the tokenizer has reached the end of input.
     */
    protected function isAtEnd(): bool
    {
        return $this->position >= $this->length;
    }

    /**
     * Emit the current token and discard the raw buffer.
     */
    protected function emitToken(Token $token): void
    {
        $this->tokens[] = $token;

        $this->buffer = '';
    }

    /**
     * Move to a position and discard the accumulated buffer.
     */
    protected function rewind(int $position): void
    {
        $this->position = $position;
        $this->buffer = '';
    }

    /**
     * Emit any accumulated buffer as a given token.
     */
    protected function flushBuffer(string $class = TextToken::class): void
    {
        if ($this->buffer !== '') {
            $this->tokens[] = new $class($this->buffer);

            $this->buffer = '';
        }
    }
}
