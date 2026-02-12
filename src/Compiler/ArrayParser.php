<?php

namespace Livewire\Blaze\Compiler;

use Livewire\Blaze\Exceptions\ArrayParserException;
use PhpParser\Node;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Scalar\Float_;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\ParserFactory;

/**
 * Parses PHP array expressions using nikic/php-parser.
 *
 * Validates array syntax and extracts variable names from component
 * directive expressions like @props and @aware.
 *
 * Returns a natural PHP array mirroring the input expression:
 * - Numeric keys: string values (variable names without defaults)
 * - String keys: variable names with their default values
 *
 * Use is_int($key) to distinguish required variables from those with defaults.
 */
class ArrayParser
{
    /**
     * Parse a PHP array expression into a native PHP array.
     *
     * Items without a key (numeric index) are variable names without defaults.
     * Items with a string key have the key as the variable name and the value
     * as the default.
     *
     * For example: "['label', 'type' => 'button', 'disabled' => false]"
     * Returns:     ['label', 'type' => 'button', 'disabled' => false]
     *
     * @param string $expression The array expression to parse
     * @return array<int|string, mixed>
     * @throws ArrayParserException
     */
    public static function parse(string $expression): array
    {
        $arrayNode = static::parseToArrayNode($expression);

        $items = [];

        foreach ($arrayNode->items as $item) {
            if ($item === null) {
                continue;
            }

            if ($item->key === null) {
                // Numeric key — value must be a string literal (variable name)
                if (! $item->value instanceof String_) {
                    throw new ArrayParserException($expression, 'value must be a string literal');
                }

                $items[] = $item->value->value;
            } else {
                // String key — variable name with default value
                if (! $item->key instanceof String_) {
                    throw new ArrayParserException($expression, 'key must be a string literal');
                }

                $items[$item->key->value] = static::evaluateNode($item->value);
            }
        }

        return $items;
    }

    /**
     * Parse expression string to Array_ node.
     */
    protected static function parseToArrayNode(string $expression): Array_
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
     * Evaluate an AST node to a PHP value.
     *
     * Returns the actual value for simple scalar types (string, int, float,
     * bool, null). For complex expressions (closures, function calls, etc.),
     * returns true as a marker indicating a default exists.
     */
    protected static function evaluateNode(Node $node): mixed
    {
        return match (true) {
            $node instanceof String_ => $node->value,
            $node instanceof Int_ => $node->value,
            $node instanceof Float_ => $node->value,
            $node instanceof ConstFetch => match (strtolower($node->name->toString())) {
                'true' => true,
                'false' => false,
                'null' => null,
                default => true,
            },
            $node instanceof Array_ => static::evaluateArrayNode($node),
            default => true,
        };
    }

    /**
     * Evaluate an Array_ node recursively.
     */
    protected static function evaluateArrayNode(Array_ $node): array
    {
        $result = [];

        foreach ($node->items as $item) {
            if ($item === null) {
                continue;
            }

            $value = static::evaluateNode($item->value);

            if ($item->key !== null) {
                $key = static::evaluateNode($item->key);
                $result[$key] = $value;
            } else {
                $result[] = $value;
            }
        }

        return $result;
    }
}
