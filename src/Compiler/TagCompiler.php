<?php

namespace Livewire\Blaze\Compiler;

use Illuminate\View\Compilers\ComponentTagCompiler;
use Livewire\Blaze\Directive\BlazeDirective;
use Livewire\Blaze\Nodes\ComponentNode;
use Livewire\Blaze\Nodes\SlotNode;
use Livewire\Blaze\Nodes\TextNode;
use Livewire\Blaze\Nodes\Node;
use Closure;
use Livewire\Blaze\Support\AttributeParser;

class TagCompiler
{
    protected Closure $componentNameToPath;
    protected ComponentTagCompiler $componentTagCompiler;

    public function __construct(callable $componentNameToPath)
    {
        $this->componentNameToPath = $componentNameToPath;
        $this->componentTagCompiler = new ComponentTagCompiler(
            aliases: [],
            namespaces: [],
            blade: app('blade.compiler'),
        );
    }

    /**
     * Check if a component has @blaze without fold/memo.
     */
    protected function isBlazeComponent(string $componentPath): bool
    {
        $source = file_get_contents($componentPath);
        $params = BlazeDirective::getParameters($source);

        return !is_null($params);
    }

    /**
     * Compile a component node into ensureCompiled + require_once + function call.
     */
    public function compile(Node $node): Node
    {
        if (! $node instanceof ComponentNode) {
            return $node;
        }

        if ($node->name === 'flux::delegate-component') {
            return new TextNode($this->compileDelegateComponent($node));
        }

        $componentPath = ($this->componentNameToPath)($node->name);

        if (empty($componentPath) || ! $this->isBlazeComponent($componentPath)) {
            return $node;
        }

        if ($this->hasDynamicSlotNames($node)) {
            return $node;
        }

        $hash = self::hash($componentPath);
        $functionName = '_' . $hash;
        $slotsVariableName = '$slot' . $hash;
        [$attributesArrayString, $boundKeysArrayString] = $this->getAttributesAndBoundKeysArrayStrings($node->attributes);

        $output = '<' . '?php $__blaze->ensureCompiled(\'' . $componentPath . '\', __DIR__.\'/'. $hash . '.php\'); ?>' . "\n";
        $output .= '<' . '?php require_once __DIR__.\'/'. $hash . '.php\'; ?>';

        $output .= "\n" . '<' . '?php $__blaze->pushData(' . $attributesArrayString . '); ?>';

        $output .= "\n" . $this->compileSlotsAndFunctionCall($node, $functionName, $slotsVariableName, $attributesArrayString, $boundKeysArrayString);

        $output .= "\n" . '<' . '?php $__blaze->popData(); ?>' . "\n";

        return new TextNode($output);
    }

    protected function compileDelegateComponent(ComponentNode $node)
    {
        $attributeParser = new AttributeParser;
        $attributesArray = $attributeParser->parseAttributeStringToArray($node->attributes);
        $componentName = "'flux::' . " . $attributesArray['component']['value'];

        $output = '<' . '?php $__resolved = $__blaze->resolve(' . $componentName . '); ?>' . "\n";
        $output .= '<' . '?php require_once __DIR__ . \'/\' . $__resolved . \'.php\'; ?>' . "\n";

        $slotsVariableName = '$slots' . hash('xxh128', $componentName);

        $output .= $this->compileSlotsAndFunctionCall($node, '(\'_\' . $__resolved)', $slotsVariableName, '$__blaze->currentComponentData()', '[]');
        $output .= "\n" . '<' . '?php unset($__resolved) ?>' . "\n";

        return $output;
    }

    protected function compileSlotsAndFunctionCall(ComponentNode $node, string $function, string $slotsVariableName, string $attributesArrayString, string $boundKeysArrayString): string
    {
        $output = '';

        if ($node->selfClosing) {
            $output .= '<' . '?php ' . $function . '($__blaze, ' . $attributesArrayString . ', [], ' . $boundKeysArrayString . '); ?>';
        } else {
            $slotCompiler = new SlotCompiler($slotsVariableName, fn ($str) => $this->getAttributesAndBoundKeysArrayStrings($str, true)[0]);

            $output .= '<' . '?php ' . $slotsVariableName . ' = []; ?>';
            $output .= $slotCompiler->compile($node->children);
            $output .= "\n" . '<' . '?php ' . $function . '($__blaze, ' . $attributesArrayString . ', ' . $slotsVariableName . ', ' . $boundKeysArrayString . '); ?>';
        }

        return $output;
    }

    /**
     * Check if any slot has a dynamic name (:name="$var").
     */
    protected function hasDynamicSlotNames(ComponentNode $node): bool
    {
        foreach ($node->children as $child) {
            if ($child instanceof SlotNode && str_starts_with($child->name, '$')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Convert attribute string to PHP array syntax using Laravel's ComponentTagCompiler.
     *
     * @param bool $escapeBound Whether to wrap bound values in sanitizeComponentAttribute()
     * @return array{string, string} Tuple of [attributesArrayString, boundKeysArrayString]
     */
    protected function getAttributesAndBoundKeysArrayStrings(string $attributeString, bool $escapeBound = false): array
    {
        if (empty(trim($attributeString))) {
            return ['[]', '[]'];
        }

        return (function (string $str, bool $escapeBound): array {
            /** @var ComponentTagCompiler $this */

            // We're using reflection here to avoid LSP errors
            $boundAttributesProp = new \ReflectionProperty($this, 'boundAttributes');
            $boundAttributesProp->setValue($this, []);

            // parseShortAttributeSyntax expects leading whitespace
            $str = $this->parseShortAttributeSyntax(' ' . $str);
            $attributes = $this->getAttributesFromAttributeString($str);
            $boundKeys = array_keys($boundAttributesProp->getValue($this));

            $attributesString = '[' . $this->attributesToString($attributes, $escapeBound) . ']';
            $boundKeysString = '[' . implode(', ', array_map(fn ($k) => "'{$k}'", $boundKeys)) . ']';

            return [$attributesString, $boundKeysString];
        })->call($this->componentTagCompiler, $attributeString, $escapeBound);
    }

    /**
     * Generate a unique hash for a component path.
     */
    public static function hash(string $componentPath): string
    {
        return hash('xxh128', 'v2' . $componentPath);
    }
}
