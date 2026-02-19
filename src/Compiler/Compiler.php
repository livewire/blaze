<?php

namespace Livewire\Blaze\Compiler;

use Illuminate\View\Compilers\ComponentTagCompiler;
use Livewire\Blaze\Blaze;
use Livewire\Blaze\Parser\Nodes\ComponentNode;
use Livewire\Blaze\Parser\Nodes\SlotNode;
use Livewire\Blaze\Parser\Nodes\TextNode;
use Livewire\Blaze\Parser\Nodes\Node;
use Livewire\Blaze\Config;
use Livewire\Blaze\Support\ComponentSource;
use Livewire\Blaze\Support\Utils;

/**
 * Compiles component nodes into PHP require_once + function call output.
 */
class Compiler
{
    protected Config $config;
    protected ComponentTagCompiler $blade;
    protected SlotCompiler $slotCompiler;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->slotCompiler = new SlotCompiler(fn (string $str) => $this->getAttributesAndBoundKeysArrayStrings($str, true)[0]);
        $this->blade = new ComponentTagCompiler([], [], app('blade.compiler'));
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
            return new TextNode($this->compileDelegateComponentTag($node));
        }

        $source = new ComponentSource($node->name);

        if (! $source->exists()) {
            return $node;
        }
        
        if (! $this->shouldCompile($source)) {
            return $node;
        }

        if ($this->hasDynamicSlotNames($node)) {
            return $node;
        }

        return new TextNode($this->compileComponentTag($node, $source));
    }

    /**
     * Check if a component should be compiled with Blaze.
     */
    protected function shouldCompile(ComponentSource $source): bool
    {
        if ($source->directives->blaze()) {
            return true;
        }

        return $this->config->shouldCompile($source->path)
            || $this->config->shouldMemoize($source->path)
            || $this->config->shouldFold($source->path);
    }

    /**
     * Check if any slot has a dynamic name (:name="$var").
     * 
     * TODO: Is this even real? Does Laravel support this?
     */
    protected function hasDynamicSlotNames(ComponentNode $node): bool
    {
        foreach ($node->children as $child) {
            if ($child instanceof SlotNode && str_starts_with($child->name, '$')) { // TODO: Double check this
                return true;
            }
        }

        return false;
    }

    /**
     * Compile a standard component tag into ensureCompiled + require_once + function call.
     */
    protected function compileComponentTag(ComponentNode $node, ComponentSource $source): string
    {
        $hash = Utils::hash($source->path);
        $functionName = (Blaze::isFolding() ? '__' : '_') . $hash;
        $slotsVariableName = '$slots' . $hash;
        [$attributesArrayString, $boundKeysArrayString] = $this->getAttributesAndBoundKeysArrayStrings($node->attributeString);

        $output = '<' . '?php $__blaze->ensureCompiled(\'' . $source->path . '\', $__blaze->compiledPath.\'/'. $hash . '.php\'); ?>' . "\n";
        $output .= '<' . '?php require_once $__blaze->compiledPath.\'/'. $hash . '.php\'; ?>';

        if ($node->selfClosing) {
            $output .= "\n" . '<' . '?php $__blaze->pushData(' . $attributesArrayString . '); ?>';
            $output .= "\n" . '<' . '?php ' . $functionName . '($__blaze, ' . $attributesArrayString . ', [], ' . $boundKeysArrayString . ', isset($this) ? $this : null); ?>';
        } else {
            $attributesVariableName = '$__attrs' . $hash;
            $output .= "\n" . '<' . '?php ' . $attributesVariableName . ' = ' . $attributesArrayString . '; ?>';
            $output .= "\n" . '<' . '?php $__blaze->pushData(' . $attributesVariableName . '); ?>';
            $output .= "\n" . $this->slotCompiler->compile($slotsVariableName, $node->children);
            $output .= "\n" . '<' . '?php $__blaze->pushSlots(' . $slotsVariableName . '); ?>';
            $output .= "\n" . '<' . '?php ' . $functionName . '($__blaze, ' . $attributesVariableName . ', ' . $slotsVariableName . ', ' . $boundKeysArrayString . ', isset($this) ? $this : null); ?>';
        }

        $output .= "\n" . '<' . '?php $__blaze->popData(); ?>';

        return $output;
    }

    /**
     * Compile a flux:delegate-component tag into dynamic resolution code.
     */
    protected function compileDelegateComponentTag(ComponentNode $node): string
    {
        $attributesArray = Utils::parseAttributeStringToArray($node->attributeString);
        $componentName = "'flux::' . " . $attributesArray['component']->value;

        $output = '<' . '?php $__resolved = $__blaze->resolve(' . $componentName . '); ?>' . "\n";
        $output .= '<' . '?php require_once $__blaze->compiledPath . \'/\' . $__resolved . \'.php\'; ?>' . "\n";

        $output .= '<' . '?php $__blaze->pushData($attributes->all()); ?>';

        $slotsVariableName = '$slots' . hash('xxh128', $componentName);

        $functionName = '(\'' . (Blaze::isFolding() ? '__' : '_') . '\' . $__resolved)';

        if ($node->selfClosing) {
            $output .= "\n" . '<' . '?php ' . $functionName . '($__blaze, $attributes->all(), $__blaze->mergedComponentSlots(), [], isset($this) ? $this : null); ?>';
        } else {
            $output .= $this->slotCompiler->compile($slotsVariableName, $node->children);
            $output .= "\n" . '<' . '?php ' . $slotsVariableName . ' = array_merge($__blaze->mergedComponentSlots(), ' . $slotsVariableName . '); ?>';
            $output .= "\n" . '<' . '?php ' . $functionName . '($__blaze, $attributes->all(), ' . $slotsVariableName . ', [], isset($this) ? $this : null); ?>';
        }

        $output .= "\n" . '<' . '?php $__blaze->popData(); ?>';
        $output .= "\n" . '<' . '?php unset($__resolved) ?>' . "\n";

        return $output;
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
        })->call($this->blade, $attributeString, $escapeBound);
    }

}
