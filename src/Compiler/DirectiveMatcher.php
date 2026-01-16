<?php

namespace Livewire\Blaze\Compiler;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use ParseError;

/**
 * Directly reflects the directive parsing logic from Laravel's BladeCompiler.
 */
class DirectiveMatcher
{
    /**
     * Find all occurrences of a directive with balanced parentheses.
     *
     * @return array Array of matches: [['match' => ..., 'expression' => ...], ...]
     */
    public function match(string $template, string $directive): array
    {
        // Same pattern as Laravel's compileStatements, but with specific directive name
        $pattern = '/\B@(@?' . preg_quote($directive, '/') . ')([ \t]*)(\( ( [\S\s]*? ) \))?/x';

        return $this->matchWithPattern($template, $pattern, includeName: false);
    }

    /**
     * Find all directives with expressions in a template.
     *
     * Uses Laravel's directive pattern to match any @directive(expression).
     *
     * @return array Array of matches: [['name' => ..., 'match' => ..., 'expression' => ...], ...]
     */
    public function matchAll(string $template): array
    {
        // Laravel's pattern from compileStatements
        $pattern = '/\B@(@?\w+(?:::\w+)?)([ \t]*)(\( ( [\S\s]*? ) \))?/x';

        return $this->matchWithPattern($template, $pattern, includeName: true);
    }

    /**
     * Replace all occurrences of a directive with callback result.
     *
     * @param callable $callback Function receiving (match, expression) and returning replacement
     */
    public function replace(string $template, string $directive, callable $callback): string
    {
        $matches = $this->match($template, $directive);
        $offset = 0;

        foreach ($matches as $match) {
            $replacement = $callback($match['match'], $match['expression']);
            [$template, $offset] = $this->replaceFirst(
                $match['match'],
                $replacement,
                $template,
                $offset
            );
        }

        return $template;
    }

    /**
     * Strip a directive and trailing whitespace from template.
     */
    public function strip(string $template, string $directive): string
    {
        $matches = $this->match($template, $directive);

        foreach ($matches as $match) {
            $position = strpos($template, $match['match']);
            if ($position === false) {
                continue;
            }

            $afterPosition = $position + strlen($match['match']);
            $afterContent = substr($template, $afterPosition);

            // Strip trailing whitespace (including blank lines)
            preg_match('/^\s*/', $afterContent, $whitespaceMatch);
            $whitespaceLength = strlen($whitespaceMatch[0] ?? '');

            $template = substr($template, 0, $position) . substr($template, $afterPosition + $whitespaceLength);
        }

        return $template;
    }

    /**
     * Extract the expression from the first occurrence of a directive.
     */
    public function extractExpression(string $template, string $directive): ?string
    {
        $matches = $this->match($template, $directive);

        if (empty($matches)) {
            return null;
        }

        $expression = $matches[0]['expression'];

        return $expression !== null ? trim($expression) : null;
    }

    /**
     * Check if a directive exists in the template.
     */
    public function has(string $template, string $directive): bool
    {
        return ! empty($this->match($template, $directive));
    }

    /**
     * Core matching logic shared by match() and matchAll().
     */
    protected function matchWithPattern(
        string $template,
        string $pattern,
        bool $includeName = false,
    ): array {
        preg_match_all($pattern, $template, $matches);

        $results = [];

        for ($i = 0; isset($matches[0][$i]); $i++) {
            $name = $matches[1][$i];
            $full = $matches[0][$i];
            $parensWithContent = $matches[3][$i] ?: null;

            // Skip escaped directives (@@directive)
            if (str_starts_with($name, '@')) {
                continue;
            }

            // Recursively extend match to find proper closing parenthesis
            while ($parensWithContent !== null &&
                   Str::endsWith($full, ')') &&
                   ! $this->hasEvenNumberOfParentheses($full)) {
                if (($after = Str::after($template, $full)) === $template) {
                    break;
                }

                $rest = Str::before($after, ')');

                // Skip matches that would be consumed by this extension
                if (isset($matches[0][$i + 1]) && Str::contains($rest . ')', $matches[0][$i + 1])) {
                    unset($matches[0][$i + 1]);
                    $i++;
                }

                $full .= $rest . ')';
                $parensWithContent .= $rest . ')';
            }

            // Derive expression by removing outer () from parensWithContent
            $expression = $parensWithContent;
            if ($expression !== null && str_starts_with($expression, '(') && str_ends_with($expression, ')')) {
                $expression = substr($expression, 1, -1);
            }

            $result = [
                'match' => $full,
                'expression' => $expression,
            ];

            if ($includeName) {
                $result = ['name' => $name] + $result;
            }

            $results[] = $result;
        }

        return $results;
    }

    /**
     * Replace the first occurrence of a search string starting from offset.
     *
     * @return array [modified template, new offset]
     */
    protected function replaceFirst(string $search, string $replace, string $subject, int $offset): array
    {
        if ($search === '') {
            return [$subject, 0];
        }

        $position = strpos($subject, $search, $offset);

        if ($position !== false) {
            return [
                substr_replace($subject, $replace, $position, strlen($search)),
                $position + strlen($replace),
            ];
        }

        return [$subject, 0];
    }

    /**
     * Determine if the expression has balanced parentheses using PHP tokenizer.
     */
    protected function hasEvenNumberOfParentheses(string $expression): bool
    {
        try {
            $tokens = token_get_all('<?php ' . $expression);
        } catch (ParseError) {
            return false;
        }

        if (Arr::last($tokens) !== ')') {
            return false;
        }

        $opening = 0;
        $closing = 0;

        foreach ($tokens as $token) {
            if ($token == ')') {
                $closing++;
            } elseif ($token == '(') {
                $opening++;
            }
        }

        return $opening === $closing;
    }
}
