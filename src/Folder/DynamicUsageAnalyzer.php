<?php

namespace Livewire\Blaze\Folder;

use Livewire\Blaze\Compiler\ArrayParser;
use Livewire\Blaze\Compiler\DirectiveMatcher;
use Livewire\Blaze\Directive\BlazeDirective;
use Livewire\Blaze\Nodes\ComponentNode;
use Livewire\Blaze\Parser\Parser;
use Livewire\Blaze\Support\AttributeParser;
use Livewire\Blaze\Tokenizer\Tokenizer;
use Illuminate\Support\Str;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Expr\Variable;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

/**
 * Analyzes component source to determine if dynamic props can be safely folded.
 */
class DynamicUsageAnalyzer
{
    public function __construct(
        protected DirectiveMatcher $matcher = new DirectiveMatcher,
        protected ArrayParser $parser = new ArrayParser,
        protected AttributeParser $attributeParser = new AttributeParser,
        protected Tokenizer $tokenizer = new Tokenizer,
        protected Parser $blazeParser = new Parser,
        protected AttributeBagAnalyzer $bagAnalyzer = new AttributeBagAnalyzer,
    ) {}

    /**
     * Analyze if component can be safely folded with given dynamic props.
     *
     * Returns false (abort folding) when dynamic props are used in ways that
     * would produce incorrect results at compile-time.
     *
     * @param string $source The component template source
     * @param array $dynamicAttributes Dynamic attribute names passed to this component
     * @param callable|null $componentNameToPath Callback to resolve component name to path (enables nested analysis)
     * @param array $analyzedPaths Already analyzed paths for cycle detection
     */
    public function canFold(
        string $source,
        array $dynamicAttributes,
        ?callable $componentNameToPath = null,
        array $analyzedPaths = [],
    ): bool {
        if (empty($dynamicAttributes)) {
            return true;
        }

        // Convert kebab-case prop names to camelCase (Laravel convention)
        // e.g., 'data-test' -> 'dataTest'
        $dynamicProps = array_map(fn ($name) => Str::camel($name), $dynamicAttributes);

        // Strip @unblaze blocks - content there is handled separately
        $strippedSource = $this->stripUnblazeBlocks($source);

        $definedProps = $this->extractPropsFromDirective($strippedSource);
        $hasPropsDirective = $definedProps !== null;

        // Dynamic props NOT in @props are only accessible via $attributes
        $undefinedDynamicProps = [];
        if ($hasPropsDirective) {
            $undefinedDynamicProps = array_diff($dynamicProps, $definedProps);

            // If dynamic props aren't in @props and $attributes is used in PHP context,
            // we can't trace how those props are used
            if (! empty($undefinedDynamicProps) && $this->attributesUsedInPhpContext($strippedSource)) {
                return false;
            }
        } else {
            // No @props = all props available in both variables AND $attributes
            $undefinedDynamicProps = $dynamicProps;

            // If $attributes is used in PHP context, we can't safely fold
            if ($this->attributesUsedInPhpContext($strippedSource)) {
                return false;
            }
        }

        // Analyze variable usage patterns for each dynamic prop
        foreach ($dynamicProps as $propName) {
            if ($this->isUsedInPhpBlock($strippedSource, $propName)) {
                return false;
            }

            if ($this->isUsedInBladeDirective($strippedSource, $propName)) {
                return false;
            }

            if ($this->hasTransformedEcho($strippedSource, $propName)) {
                return false;
            }

            if ($this->hasTransformedComponentAttribute($strippedSource, $propName)) {
                return false;
            }
        }

        // Phase 9.2: Recursively analyze child components for forwarded props
        if ($componentNameToPath !== null) {
            if (! $this->analyzeChildComponents(
                source: $strippedSource,
                dynamicProps: $dynamicProps,
                undefinedDynamicProps: $undefinedDynamicProps,
                componentNameToPath: $componentNameToPath,
                analyzedPaths: $analyzedPaths,
            )) {
                return false;
            }
        }

        return true;
    }

    /**
     * Extract prop names from @props directive.
     *
     * @return array|null Array of prop names, or null if no @props directive
     */
    protected function extractPropsFromDirective(string $source): ?array
    {
        $expression = $this->matcher->extractExpression($source, 'props');

        if ($expression === null) {
            return null;
        }

        $items = $this->parser->parse($expression);

        return array_keys($items);
    }

    /**
     * Check if $attributes is used in a PHP context (not just echoed).
     *
     * Safe (echo context): {{ $attributes }}, {{ $attributes->merge([...]) }}
     * Unsafe (PHP context): @php blocks, standard PHP blocks, directive expressions
     */
    protected function attributesUsedInPhpContext(string $source): bool
    {
        // Check if $attributes appears in @php blocks
        if (preg_match_all('/@php\b(.*?)@endphp/s', $source, $matches)) {
            foreach ($matches[1] as $block) {
                if (str_contains($block, '$attributes')) {
                    return true;
                }
            }
        }

        // Check if $attributes appears in standard PHP blocks
        if (preg_match_all('/<\?php(.*?)\?>/s', $source, $matches)) {
            foreach ($matches[1] as $block) {
                if (str_contains($block, '$attributes')) {
                    return true;
                }
            }
        }

        // Check if $attributes is used in Blade directive expressions
        $directives = $this->matcher->matchAll($source);

        foreach ($directives as $directive) {
            if ($directive['expression'] === null) {
                continue;
            }

            // Skip @props - $attributes can be safely referenced there
            if ($directive['name'] === 'props') {
                continue;
            }

            if (str_contains($directive['expression'], '$attributes')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a prop is used inside a PHP block.
     */
    protected function isUsedInPhpBlock(string $source, string $propName): bool
    {
        // Check @php blocks
        if (preg_match_all('/@php\b(.*?)@endphp/s', $source, $matches)) {
            foreach ($matches[1] as $block) {
                if ($this->containsVariable($block, $propName)) {
                    return true;
                }
            }
        }

        // Check standard PHP blocks
        if (preg_match_all('/<\?php(.*?)\?>/s', $source, $matches)) {
            foreach ($matches[1] as $block) {
                if ($this->containsVariable($block, $propName)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if a prop is used in any Blade directive expression.
     *
     * Uses DirectiveMatcher::matchAll() to find all directives dynamically,
     * supporting both built-in and custom directives.
     */
    protected function isUsedInBladeDirective(string $source, string $propName): bool
    {
        $directives = $this->matcher->matchAll($source);

        foreach ($directives as $directive) {
            // Skip directives without expressions
            if ($directive['expression'] === null) {
                continue;
            }

            // Skip @props and @aware - these define variables, not use them
            if (in_array($directive['name'], ['props', 'aware'], true)) {
                continue;
            }

            if ($this->containsVariable($directive['expression'], $propName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a prop is being transformed/operated on within an echo.
     *
     * Safe patterns (prop value passes through unchanged):
     * - {{ $prop }}
     * - {!! $prop !!}
     * - {{ $something->method($prop) }} (prop passed as argument)
     * - {{ $attributes->merge(['key' => $prop]) }} (prop as array value)
     *
     * Unsafe patterns (prop value is transformed):
     * - {{ $prop ?? 'default' }} (null coalesce)
     * - {{ $prop ?: 'default' }} (elvis operator)
     * - {{ $prop ? a : b }} (ternary)
     * - {{ $prop->method() }} (method call ON prop)
     * - {{ $prop['key'] }} (array access ON prop)
     * - {{ $prop . 'suffix' }} (concatenation)
     * - {{ strtoupper($prop) }} (function with prop as subject - ambiguous, allow)
     */
    protected function hasTransformedEcho(string $source, string $propName): bool
    {
        // Find all Blade echoes: {{ ... }} and {!! ... !!}
        $patterns = [
            '/\{\{\s*(.*?)\s*\}\}/s',   // {{ ... }}
            '/\{!!\s*(.*?)\s*!!\}/s',   // {!! ... !!}
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $source, $matches)) {
                foreach ($matches[1] as $expression) {
                    if (! $this->containsVariable($expression, $propName)) {
                        continue;
                    }

                    if ($this->propIsTransformedInExpression($expression, $propName)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Check if a prop is transformed in a nested component's dynamic attributes.
     *
     * Safe: <x-card :foo="$bar">
     * Unsafe: <x-card :foo="strtoupper($bar)">
     */
    protected function hasTransformedComponentAttribute(string $source, string $propName): bool
    {
        $componentNodes = $this->extractComponentNodes($source);

        foreach ($componentNodes as $node) {
            if (empty($node->attributes)) {
                continue;
            }

            // Parse attributes to find dynamic ones
            $attributes = $this->attributeParser->parseAttributeStringToArray($node->attributes);

            foreach ($attributes as $attrName => $attrData) {
                // Only check dynamic attributes (:attr="expression")
                if (! $attrData['isDynamic']) {
                    continue;
                }

                $expression = $attrData['value'];

                // Check if this expression contains our prop
                if (! $this->containsVariable($expression, $propName)) {
                    continue;
                }

                // Check if the prop is transformed in this expression
                if ($this->propIsTransformedInExpression($expression, $propName)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Extract all ComponentNode instances from source using Blaze tokenizer/parser.
     *
     * @return ComponentNode[]
     */
    protected function extractComponentNodes(string $source): array
    {
        try {
            $tokens = $this->tokenizer->tokenize($source);
            $ast = $this->blazeParser->parse($tokens);
        } catch (\Throwable) {
            return [];
        }

        return $this->collectComponentNodes($ast);
    }

    /**
     * Recursively collect all ComponentNode instances from AST.
     *
     * @return ComponentNode[]
     */
    protected function collectComponentNodes(array $nodes): array
    {
        $components = [];

        foreach ($nodes as $node) {
            if ($node instanceof ComponentNode) {
                $components[] = $node;

                // Also check children for nested components
                if (! empty($node->children)) {
                    $components = array_merge($components, $this->collectComponentNodes($node->children));
                }
            }
        }

        return $components;
    }

    /**
     * Check if a prop is being transformed in an expression using AST analysis.
     *
     * Safe contexts (prop passes through unchanged):
     * - Just the variable: $prop
     * - Array value: ['key' => $prop]
     * - Argument to $attributes->*() methods
     *
     * Unsafe contexts (prop is transformed):
     * - Binary operations: $prop . 'x', $prop + 1
     * - Ternary/coalesce: $prop ?? 'default', $prop ? a : b
     * - Method/property on prop: $prop->method(), $prop->value
     * - Array access on prop: $prop['key']
     * - Function call argument (except $attributes methods)
     */
    protected function propIsTransformedInExpression(string $expression, string $propName): bool
    {
        $code = '<?php ' . $expression . ';';

        try {
            $parser = (new ParserFactory)->createForNewestSupportedVersion();
            $ast = $parser->parse($code);
        } catch (\Throwable) {
            // If parsing fails, fall back to simple presence check
            return false;
        }

        if ($ast === null) {
            return false;
        }

        $finder = new NodeFinder;
        $variables = $finder->find($ast, fn (Node $node) =>
            $node instanceof Variable && $node->name === $propName
        );

        foreach ($variables as $variable) {
            if ($this->variableIsTransformed($variable, $ast)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a variable node is in a transformed context.
     */
    protected function variableIsTransformed(Variable $variable, array $ast): bool
    {
        $parent = $this->findParentNode($variable, $ast);

        if ($parent === null) {
            // Variable is the entire expression - safe
            return false;
        }

        // Safe: Variable is an array item value ['key' => $prop]
        if ($parent instanceof ArrayItem && $parent->value === $variable) {
            return false;
        }

        // Safe: Variable is argument to $attributes->*() method
        if ($parent instanceof Arg) {
            $grandparent = $this->findParentNode($parent, $ast);
            if ($grandparent instanceof MethodCall || $grandparent instanceof NullsafeMethodCall) {
                $obj = $grandparent->var;
                if ($obj instanceof Variable && $obj->name === 'attributes') {
                    return false;
                }
            }
        }

        // Unsafe: Any binary operation
        if ($parent instanceof BinaryOp) {
            return true;
        }

        // Unsafe: Ternary expression
        if ($parent instanceof Ternary) {
            return true;
        }

        // Unsafe: Method call ON the prop: $prop->method()
        if ($parent instanceof MethodCall && $parent->var === $variable) {
            return true;
        }
        if ($parent instanceof NullsafeMethodCall && $parent->var === $variable) {
            return true;
        }

        // Unsafe: Property fetch ON the prop: $prop->property
        if ($parent instanceof PropertyFetch && $parent->var === $variable) {
            return true;
        }
        if ($parent instanceof NullsafePropertyFetch && $parent->var === $variable) {
            return true;
        }

        // Unsafe: Array access ON the prop: $prop['key']
        if ($parent instanceof ArrayDimFetch && $parent->var === $variable) {
            return true;
        }

        // Unsafe: Function call argument (non-$attributes)
        if ($parent instanceof Arg) {
            $grandparent = $this->findParentNode($parent, $ast);
            if ($grandparent instanceof FuncCall) {
                return true;
            }
            // Method call on something other than $attributes
            if ($grandparent instanceof MethodCall || $grandparent instanceof NullsafeMethodCall) {
                $obj = $grandparent->var;
                if (! ($obj instanceof Variable && $obj->name === 'attributes')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Find the parent node of a given node in the AST.
     */
    protected function findParentNode(Node $target, array $ast): ?Node
    {
        $finder = new NodeFinder;

        $parent = $finder->findFirst($ast, function (Node $node) use ($target) {
            foreach ($node->getSubNodeNames() as $name) {
                $subNode = $node->$name;

                if ($subNode === $target) {
                    return true;
                }

                if (is_array($subNode)) {
                    foreach ($subNode as $item) {
                        if ($item === $target) {
                            return true;
                        }
                    }
                }
            }

            return false;
        });

        return $parent;
    }

    /**
     * Check if content contains a variable reference.
     */
    protected function containsVariable(string $content, string $propName): bool
    {
        // Match $propName as a variable (word boundary after name)
        return (bool) preg_match('/\$' . preg_quote($propName, '/') . '\b/', $content);
    }

    /**
     * Strip @unblaze blocks from source.
     */
    protected function stripUnblazeBlocks(string $source): string
    {
        return preg_replace('/@unblaze.*?@endunblaze/s', '', $source);
    }

    /**
     * Analyze child components for unsafe forwarded prop usage.
     *
     * @param string $source The parent component source
     * @param array $dynamicProps All dynamic props passed to parent (camelCase)
     * @param array $undefinedDynamicProps Props not in @props (in $attributes)
     * @param callable $componentNameToPath Callback to resolve component paths
     * @param array $analyzedPaths Already analyzed paths for cycle detection
     */
    protected function analyzeChildComponents(
        string $source,
        array $dynamicProps,
        array $undefinedDynamicProps,
        callable $componentNameToPath,
        array $analyzedPaths,
    ): bool {
        $componentNodes = $this->extractComponentNodes($source);

        foreach ($componentNodes as $node) {
            // Collect all props forwarded to this child
            $forwardedProps = $this->getForwardedProps($node, $dynamicProps);

            // Also check $attributes forwarding
            $attributesForwarding = $this->getForwardedFromAttributes($node, $undefinedDynamicProps);
            if ($attributesForwarding === null) {
                // Unanalyzable $attributes chain - abort
                return false;
            }

            // Merge individual props and $attributes forwarding
            $forwardedProps = array_merge($forwardedProps, $attributesForwarding);

            if (empty($forwardedProps)) {
                continue;
            }

            // Resolve child component path
            $childPath = $componentNameToPath($node->name);

            if (empty($childPath) || ! file_exists($childPath)) {
                // Can't resolve child - skip (optimistic)
                continue;
            }

            // Cycle detection
            if (in_array($childPath, $analyzedPaths, true)) {
                continue;
            }

            $childSource = file_get_contents($childPath);

            // Check if child is Blaze-foldable
            if (! $this->isBlazeFoldable($childSource)) {
                // Non-Blaze children are skipped (optimistic approach)
                continue;
            }

            // Map parent prop names to child prop names for recursive analysis
            $childPropNames = array_values($forwardedProps);

            // Recursively analyze the child
            if (! $this->canFold(
                source: $childSource,
                dynamicAttributes: $childPropNames,
                componentNameToPath: $componentNameToPath,
                analyzedPaths: [...$analyzedPaths, $childPath],
            )) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get props forwarded through individual dynamic attributes.
     *
     * Detects patterns like:
     * - :title="$name" -> ['name' => 'title']
     * - :$type -> ['type' => 'type']
     *
     * @param ComponentNode $node The child component node
     * @param array $dynamicProps Props that are dynamic in the parent
     * @return array<string, string> parentProp => childProp mappings
     */
    protected function getForwardedProps(ComponentNode $node, array $dynamicProps): array
    {
        if (empty($node->attributes)) {
            return [];
        }

        $forwarded = [];
        $attributes = $this->attributeParser->parseAttributeStringToArray($node->attributes);

        foreach ($attributes as $attrName => $attrData) {
            if (! $attrData['isDynamic']) {
                continue;
            }

            $expression = trim($attrData['value']);

            // Skip $attributes forwarding - handled separately
            if (str_starts_with($expression, '$attributes')) {
                continue;
            }

            // Check if this is a simple variable reference
            $parentProp = $this->extractSimpleVariableReference($expression);

            if ($parentProp === null) {
                // Not a simple variable - transformation check already done
                continue;
            }

            // Only track if this is one of the dynamic props
            if (in_array($parentProp, $dynamicProps, true)) {
                $forwarded[$parentProp] = Str::camel($attrName);
            }
        }

        return $forwarded;
    }

    /**
     * Get props forwarded through $attributes bag.
     *
     * Detects patterns like:
     * - :$attributes -> forward all undeclared props
     * - :attributes="$attributes->except(['id'])" -> forward filtered props
     *
     * @param ComponentNode $node The child component node
     * @param array $undefinedDynamicProps Props in parent's $attributes bag
     * @return array<string, string>|null parentProp => childProp, null if unanalyzable
     */
    protected function getForwardedFromAttributes(ComponentNode $node, array $undefinedDynamicProps): ?array
    {
        if (empty($node->attributes)) {
            return [];
        }

        $attributes = $this->attributeParser->parseAttributeStringToArray($node->attributes);

        foreach ($attributes as $attrName => $attrData) {
            if (! $attrData['isDynamic']) {
                continue;
            }

            $expression = trim($attrData['value']);

            // Check if this involves $attributes
            if (! str_starts_with($expression, '$attributes')) {
                continue;
            }

            // Analyze the $attributes method chain
            $result = $this->bagAnalyzer->analyze($expression);

            if ($result === null) {
                // Unanalyzable chain - abort folding
                return null;
            }

            // Resolve which props are actually forwarded
            return $result->resolveForwarding($undefinedDynamicProps);
        }

        return [];
    }

    /**
     * Extract variable name from a simple variable reference.
     *
     * Returns the variable name (without $) if expression is exactly "$varName",
     * or null if it's a more complex expression.
     */
    protected function extractSimpleVariableReference(string $expression): ?string
    {
        $expression = trim($expression);

        // Match simple variable: $name (with word boundary)
        if (preg_match('/^\$([a-zA-Z_][a-zA-Z0-9_]*)$/', $expression, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Check if a component source is Blaze-foldable.
     */
    protected function isBlazeFoldable(string $source): bool
    {
        $parameters = BlazeDirective::getParameters($source);

        if ($parameters === null) {
            return false;
        }

        return $parameters['fold'] ?? false;
    }
}
