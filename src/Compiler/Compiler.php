<?php

namespace Livewire\Blaze\Compiler;

use Illuminate\View\Compilers\ComponentTagCompiler;
use Livewire\Blaze\BladeService;
use Livewire\Blaze\BlazeManager;
use Livewire\Blaze\Parser\Nodes\ComponentNode;
use Livewire\Blaze\Parser\Nodes\SlotNode;
use Livewire\Blaze\Parser\Nodes\TextNode;
use Livewire\Blaze\Parser\Nodes\Node;
use Livewire\Blaze\Config;
use Livewire\Blaze\Support\ComponentSource;
use Livewire\Blaze\Support\Utils;

/**
 * Compiles component nodes into PHP function call output.
 */
class Compiler
{
    protected ComponentTagCompiler $tagCompiler;
    protected SlotCompiler $slotCompiler;

    public function __construct(
        protected Config $config,
        protected BladeService $blade,
        protected BlazeManager $manager,
    ) {
        $this->slotCompiler = new SlotCompiler($manager, fn (string $str) => $this->getAttributesAndBoundKeysArrayStrings($str, true)[0]);
        $this->tagCompiler = new ComponentTagCompiler([], [], $blade->compiler);
    }

    /**
     * Compile a component node into ensureRequired + function call.
     */
    public function compile(Node $node): Node
    {
        if (! $node instanceof ComponentNode) {
            return $node;
        }

        if ($node->name === 'flux::delegate-component') {
            return new TextNode($this->compileDelegateComponentTag($node));
        }

        $source = ComponentSource::for($this->blade->componentNameToPath($node->name));

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
     * Compile a standard component tag into ensureRequired + function call.
     */
    protected function compileComponentTag(ComponentNode $node, ComponentSource $source): string
    {
        $hash = Utils::hash($source->path);
        $functionName = ($this->manager->isFolding() ? '__' : '_') . $hash;
        [$attributesArrayString, $boundKeysArrayString] = $this->getAttributesAndBoundKeysArrayStrings($node->attributeString);

        $output = '<' . '?php $__blaze->ensureRequired(\'' . $source->path . '\', $__blaze->compiledPath.\'/'. $hash . '.php\'); ?>' . "\n";

        if ($node->selfClosing) {
            $output .= '<' . '?php $__blaze->pushData(' . $attributesArrayString . '); ?>' . "\n";
            $output .= '<' . '?php ' . $functionName . '($__blaze, ' . $attributesArrayString . ', [], ' . $boundKeysArrayString . ', isset($this) ? $this : null); ?>' . "\n";
        } else {
            $slotsVariableName = '$__slots' . $hash;
            $attributesVariableName = '$__attrs' . $hash;
            $output .= '<' . '?php if (isset(' . $slotsVariableName . ')) $__slotsOriginal = ' . $slotsVariableName . '; ?>' . "\n";
            $output .= '<' . '?php if (isset(' . $attributesVariableName . ')) $__attrsOriginal = ' . $attributesVariableName . '; ?>' . "\n";
            $output .= '<' . '?php ' . $attributesVariableName . ' = ' . $attributesArrayString . '; ?>' . "\n";
            $output .= '<' . '?php ' . $slotsVariableName . ' = []; ?>' . "\n";
            $output .= '<' . '?php $__blaze->pushData(' . $attributesVariableName . '); ?>' . "\n";
            $output .= $this->slotCompiler->compile($slotsVariableName, $node->children);
            $output .= '<' . '?php $__blaze->pushSlots(' . $slotsVariableName . '); ?>' . "\n";
            $output .= '<' . '?php ' . $functionName . '($__blaze, ' . $attributesVariableName . ', ' . $slotsVariableName . ', ' . $boundKeysArrayString . ', isset($this) ? $this : null); ?>' . "\n";
            $output .= '<' . '?php if (isset($__slotsOriginal)) { ' . $slotsVariableName . ' = $__slotsOriginal; unset($__slotsOriginal); } ?>' . "\n";
            $output .= '<' . '?php if (isset($__attrsOriginal)) { ' . $attributesVariableName . ' = $__attrsOriginal; unset($__attrsOriginal); } ?>' . "\n";
        }

        $output .= '<' . '?php $__blaze->popData(); ?>';

        return $output;
    }

    /**
     * Compile a flux:delegate-component tag into dynamic resolution code.
     */
    protected function compileDelegateComponentTag(ComponentNode $node): string
    {
        $componentName = "'flux::' . " . $node->attributes['component']->value;
        $functionName = '(\'' . ($this->manager->isFolding() ? '__' : '_') . '\' . $__resolved)';
        
        $output = '<' . '?php $__resolved = $__blaze->resolve(' . $componentName . '); ?>' . "\n";
        $output .= '<' . '?php $__blaze->pushData($attributes->all()); ?>' . "\n";
        $output .= '<' . '?php if ($__resolved !== false): ?>' . "\n";

        if ($node->selfClosing) {
            $output .= '<' . '?php ' . $functionName . '($__blaze, $attributes->all(), $__blaze->mergedComponentSlots(), [], isset($this) ? $this : null); ?>' . "\n";
        } else {
            $slotsVariableName = '$__slots' . Utils::hash($componentName);
            $output .= '<' . '?php if (isset(' . $slotsVariableName . ')) $__slotsOriginal = ' . $slotsVariableName . '; ?>' . "\n";
            $output .= '<' . '?php ' . $slotsVariableName . ' = []; ?>' . "\n";
            $output .= $this->slotCompiler->compile($slotsVariableName, $node->children);
            $output .= '<' . '?php ' . $slotsVariableName . ' = array_merge($__blaze->mergedComponentSlots(), ' . $slotsVariableName . '); ?>' . "\n";
            $output .= '<' . '?php ' . $functionName . '($__blaze, $attributes->all(), ' . $slotsVariableName . ', [], isset($this) ? $this : null); ?>' . "\n";
            $output .= '<' . '?php if (isset($__slotsOriginal)) { ' . $slotsVariableName . ' = $__slotsOriginal; unset($__slotsOriginal); } ?>' . "\n";
        }

        $output .= '<' . '?php else: ?>' . "\n";
        $output .= $node->render() . "\n";
        $output .= '<' . '?php endif; ?>' . "\n";

        $output .= '<' . '?php $__blaze->popData(); ?>' . "\n";
        $output .= '<' . '?php unset($__resolved) ?>';

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
        })->call($this->tagCompiler, $attributeString, $escapeBound);
    }

}
