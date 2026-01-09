<?php

namespace Livewire\Blaze\Compiler;

use Livewire\Blaze\Exceptions\ArrayParserException;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter;

/**
 * Parses PHP array expressions using nikic/php-parser.
 */
class ArrayParser
{
    protected ?PrettyPrinter\Standard $printer = null;

    /**
     * Parse an array expression and extract items.
     *
     * Returns an associative array where:
     * - Key is the variable name
     * - Value is the default value as PHP code (string) or null if no default
     *
     * @param string $expression The array expression to parse
     * @return array<string, ?string>
     * @throws ArrayParserException
     */
    public function parse(string $expression): array
    {
        $arrayNode = $this->parseToArray($expression);

        $items = [];

        foreach ($arrayNode->items as $item) {
            if ($item === null) {
                continue;
            }

            [$name, $default] = $this->parseItem($item, $expression);
            $items[$name] = $default;
        }

        return $items;
    }

    /**
     * Parse expression string to Array_ node.
     */
    protected function parseToArray(string $expression): Array_
    {
        $parser = (new ParserFactory)->createForNewestSupportedVersion();

        try {
            $ast = $parser->parse('<?php ' . $expression . ';');
        } catch (\Throwable $e) {
            throw new ArrayParserException($expression, $e->getMessage());
        }

        if (empty($ast) || ! $ast[0] instanceof Expression) {
            throw new ArrayParserException($expression, 'could not parse expression');
        }

        /** @var Expression $stmt */
        $stmt = $ast[0];

        if (! $stmt->expr instanceof Array_) {
            throw new ArrayParserException($expression, 'expression must be an array');
        }

        return $stmt->expr;
    }

    /**
     * Parse a single array item.
     *
     * @return array{string, ?string} [name, default]
     */
    protected function parseItem(ArrayItem $item, string $expression): array
    {
        // Numeric key = variable name only (no default)
        if ($item->key === null) {
            if (! $item->value instanceof String_) {
                throw new ArrayParserException($expression, 'value must be a string literal');
            }

            return [$item->value->value, null];
        }

        // String key = variable with default
        if (! $item->key instanceof String_) {
            throw new ArrayParserException($expression, 'key must be a string literal');
        }

        return [
            $item->key->value,
            $this->printer()->prettyPrintExpr($item->value),
        ];
    }

    /**
     * Get the pretty printer (lazy instantiation).
     */
    protected function printer(): PrettyPrinter\Standard
    {
        return $this->printer ??= new PrettyPrinter\Standard;
    }
}
