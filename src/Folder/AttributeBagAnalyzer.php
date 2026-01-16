<?php

namespace Livewire\Blaze\Folder;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\ArrayItem;
use PhpParser\ParserFactory;

/**
 * Analyzes $attributes method chains to determine which props are forwarded.
 *
 * Supports only statically-analyzable methods: merge, only, except, class, style.
 * Returns null for any unanalyzable method chain, signaling to abort folding.
 */
class AttributeBagAnalyzer
{
    /**
     * Allow-listed methods that can be statically analyzed.
     */
    protected const ALLOWED_METHODS = [
        'merge',
        'only',
        'except',
        'class',
        'style',
        '__invoke',
    ];

    /**
     * Analyze an expression involving $attributes.
     *
     * @param string $expression e.g. "$attributes->except(['id'])->merge(['class' => $variant])"
     * @return AttributeBagAnalysisResult|null Null = can't analyze, abort folding
     */
    public function analyze(string $expression): ?AttributeBagAnalysisResult
    {
        $expression = trim($expression);

        // Handle simple $attributes reference
        if ($expression === '$attributes') {
            return new AttributeBagAnalysisResult(
                included: null,
                excluded: [],
                renamed: [],
            );
        }

        $code = '<?php ' . $expression . ';';

        try {
            $parser = (new ParserFactory)->createForNewestSupportedVersion();
            $ast = $parser->parse($code);
        } catch (\Throwable) {
            return null;
        }

        if ($ast === null || empty($ast)) {
            return null;
        }

        // Get the expression from the statement
        $stmt = $ast[0];
        if (! $stmt instanceof Node\Stmt\Expression) {
            return null;
        }

        $expr = $stmt->expr;

        // Verify this is an $attributes chain
        if (! $this->isAttributesChain($expr)) {
            return null;
        }

        // Extract the method chain
        $methods = $this->extractMethodChain($expr);

        if ($methods === null) {
            return null;
        }

        // Process the chain to build the result
        return $this->processMethodChain($methods);
    }

    /**
     * Check if the expression is rooted in $attributes.
     */
    protected function isAttributesChain(Node $expr): bool
    {
        // Walk to the root of the method chain
        while ($expr instanceof MethodCall) {
            $expr = $expr->var;
        }

        return $expr instanceof Variable && $expr->name === 'attributes';
    }

    /**
     * Extract method calls from the chain in order.
     *
     * @return array|null Array of [methodName, args] pairs, or null if unanalyzable
     */
    protected function extractMethodChain(Node $expr): ?array
    {
        $methods = [];

        while ($expr instanceof MethodCall) {
            $methodName = $expr->name;

            // Method name must be a simple identifier
            if (! $methodName instanceof Node\Identifier) {
                return null;
            }

            $name = $methodName->name;

            // Check if method is in allow-list
            if (! in_array($name, self::ALLOWED_METHODS, true)) {
                return null;
            }

            // Prepend to maintain call order (chain is parsed inside-out)
            array_unshift($methods, [
                'name' => $name,
                'args' => $expr->args,
            ]);

            $expr = $expr->var;
        }

        return $methods;
    }

    /**
     * Process method chain to build the analysis result.
     */
    protected function processMethodChain(array $methods): ?AttributeBagAnalysisResult
    {
        $included = null;  // null = all props
        $excluded = [];
        $renamed = [];

        foreach ($methods as $method) {
            $name = $method['name'];
            $args = $method['args'];

            switch ($name) {
                case 'only':
                    $filter = $this->extractStaticStringList($args);
                    if ($filter === null) {
                        return null; // Dynamic argument, can't analyze
                    }
                    // only() restricts to specified props
                    $included = $included === null
                        ? $filter
                        : array_values(array_intersect($included, $filter));
                    break;

                case 'except':
                    $filter = $this->extractStaticStringList($args);
                    if ($filter === null) {
                        return null; // Dynamic argument, can't analyze
                    }
                    // except() excludes specified props
                    $excluded = array_merge($excluded, $filter);
                    // Also remove from included if set
                    if ($included !== null) {
                        $included = array_values(array_diff($included, $filter));
                    }
                    break;

                case 'merge':
                case '__invoke':
                    // Extract renamed props from array argument
                    $mappings = $this->extractRenamedProps($args);
                    if ($mappings === null) {
                        return null;
                    }
                    $renamed = array_merge($renamed, $mappings);
                    break;

                case 'class':
                case 'style':
                    // These methods don't affect prop forwarding
                    // They just add 'class' or 'style' attributes
                    break;
            }
        }

        return new AttributeBagAnalysisResult(
            included: $included,
            excluded: array_unique($excluded),
            renamed: $renamed,
        );
    }

    /**
     * Extract a static string list from method arguments.
     *
     * Handles: only(['a', 'b']), only('a'), except(['x'])
     *
     * @return array|null Array of strings, or null if dynamic
     */
    protected function extractStaticStringList(array $args): ?array
    {
        if (empty($args)) {
            return [];
        }

        $arg = $args[0];
        if (! $arg instanceof Node\Arg) {
            return null;
        }

        $value = $arg->value;

        // Single string: only('foo')
        if ($value instanceof String_) {
            return [$value->value];
        }

        // Array: only(['foo', 'bar'])
        if ($value instanceof Array_) {
            $items = [];
            foreach ($value->items as $item) {
                if (! $item instanceof ArrayItem) {
                    continue;
                }
                // Value must be a static string
                if (! $item->value instanceof String_) {
                    return null; // Dynamic value
                }
                $items[] = $item->value->value;
            }
            return $items;
        }

        // Any other expression is dynamic
        return null;
    }

    /**
     * Extract renamed props from merge() array argument.
     *
     * Handles: merge(['class' => $variant]) -> ['variant' => 'class']
     *
     * @return array|null parentProp => childProp mappings, or null if unanalyzable
     */
    protected function extractRenamedProps(array $args): ?array
    {
        if (empty($args)) {
            return [];
        }

        $arg = $args[0];
        if (! $arg instanceof Node\Arg) {
            return null;
        }

        $value = $arg->value;

        // Must be an array
        if (! $value instanceof Array_) {
            return null;
        }

        $renamed = [];

        foreach ($value->items as $item) {
            if (! $item instanceof ArrayItem) {
                continue;
            }

            // We only care about keyed items with variable values
            // e.g., ['class' => $variant]
            if ($item->key === null) {
                continue;
            }

            // Key must be a static string
            if (! $item->key instanceof String_) {
                continue;
            }

            $childProp = $item->key->value;

            // Value must be a simple variable for us to track the mapping
            if ($item->value instanceof Variable && is_string($item->value->name)) {
                $parentProp = $item->value->name;
                $renamed[$parentProp] = $childProp;
            }
            // If value is not a simple variable (e.g., expression, function call),
            // we don't track it as a renaming, but that's OK - it's just a static value
        }

        return $renamed;
    }
}
