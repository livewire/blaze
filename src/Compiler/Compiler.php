<?php

namespace Livewire\Blaze\Compiler;

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
    protected SlotCompiler $slotCompiler;

    public function __construct(
        protected Config $config,
        protected BladeService $blade,
        protected BlazeManager $manager,
    ) {
        $this->slotCompiler = new SlotCompiler($manager, $blade);
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
        [$attributesArrayString, $boundKeysArrayString, $originalKeysArrayString] = $this->compileAttributes($node);

        $output = '<' . '?php $__blaze->ensureRequired(\'' . $source->path . '\', $__blaze->compiledPath.\'/'. $hash . '.php\'); ?>' . "\n";

        if ($node->selfClosing) {
            $output .= '<' . '?php $__blaze->pushData(' . $attributesArrayString . '); ?>' . "\n";
            $output .= '<' . '?php ' . $functionName . '($__blaze, ' . $attributesArrayString . ', [], ' . $boundKeysArrayString . ', ' . $originalKeysArrayString . ', $__this ?? (isset($this) ? $this : null)); ?>' . "\n";
        } else {
            $slotsVariableName = '$__slots' . $hash;
            $attributesVariableName = '$__attrs' . $hash;
            $slotsStackName = '$__slotsStack' . $hash;
            $attrsStackName = '$__attrsStack' . $hash;
            $output .= '<' . '?php if (isset(' . $slotsVariableName . ')) { ' . $slotsStackName . '[] = ' . $slotsVariableName . '; } ?>' . "\n";
            $output .= '<' . '?php if (isset(' . $attributesVariableName . ')) { ' . $attrsStackName . '[] = ' . $attributesVariableName . '; } ?>' . "\n";
            $output .= '<' . '?php ' . $attributesVariableName . ' = ' . $attributesArrayString . '; ?>' . "\n";
            $output .= '<' . '?php ' . $slotsVariableName . ' = []; ?>' . "\n";
            $output .= '<' . '?php $__blaze->pushData(' . $attributesVariableName . '); ?>' . "\n";
            $output .= $this->slotCompiler->compile($slotsVariableName, $node->children);
            $output .= '<' . '?php $__blaze->pushSlots(' . $slotsVariableName . '); ?>' . "\n";
            $output .= '<' . '?php ' . $functionName . '($__blaze, ' . $attributesVariableName . ', ' . $slotsVariableName . ', ' . $boundKeysArrayString . ', ' . $originalKeysArrayString . ', $__this ?? (isset($this) ? $this : null)); ?>' . "\n";
            $output .= '<' . '?php if (! empty(' . $slotsStackName . ')) { ' . $slotsVariableName . ' = array_pop(' . $slotsStackName . '); } ?>' . "\n";
            $output .= '<' . '?php if (! empty(' . $attrsStackName . ')) { ' . $attributesVariableName . ' = array_pop(' . $attrsStackName . '); } ?>' . "\n";
        }

        $output .= '<' . '?php $__blaze->popData(); ?>';

        return $output;
    }

    /**
     * Build attribute array string, bound keys, and key map from parsed attributes.
     *
     * @return array{string, string, string} Tuple of [attributesArrayString, boundKeysArrayString, originalKeysArrayString]
     */
    protected function compileAttributes(ComponentNode $node): array
    {
        $data = [];
        $boundKeys = [];
        $originalKeys = [];

        foreach ($node->attributes as $attr) {
            $data[] = $this->blade->compileAttribute($attr);

            if ($attr->bound() || $attr->valueless) {
                $boundKeys[] = "'{$attr->propName}'";
            }

            if ($attr->propName !== $attr->name) {
                $originalKeys[] = "'{$attr->propName}' => '{$attr->name}'";
            }
        }

        return [
            '[' . implode(',', $data) . ']',
            '[' . implode(', ', $boundKeys) . ']',
            '[' . implode(', ', $originalKeys) . ']',
        ];
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
            $output .= '<' . '?php ' . $functionName . '($__blaze, $attributes->all(), $__blaze->mergedComponentSlots(), [], [], $__this ?? (isset($this) ? $this : null)); ?>' . "\n";
        } else {
            $hash = Utils::hash($componentName);
            $slotsVariableName = '$__slots' . $hash;
            $slotsStackName = '$__slotsStack' . $hash;
            $output .= '<' . '?php if (isset(' . $slotsVariableName . ')) { ' . $slotsStackName . '[] = ' . $slotsVariableName . '; } ?>' . "\n";
            $output .= '<' . '?php ' . $slotsVariableName . ' = []; ?>' . "\n";
            $output .= $this->slotCompiler->compile($slotsVariableName, $node->children);
            $output .= '<' . '?php ' . $slotsVariableName . ' = array_merge($__blaze->mergedComponentSlots(), ' . $slotsVariableName . '); ?>' . "\n";
            $output .= '<' . '?php ' . $functionName . '($__blaze, $attributes->all(), ' . $slotsVariableName . ', [], [], $__this ?? (isset($this) ? $this : null)); ?>' . "\n";
            $output .= '<' . '?php if (! empty(' . $slotsStackName . ')) { ' . $slotsVariableName . ' = array_pop(' . $slotsStackName . '); } ?>' . "\n";
        }

        $output .= '<' . '?php else: ?>' . "\n";
        $output .= $node->render() . "\n";
        $output .= '<' . '?php endif; ?>' . "\n";

        $output .= '<' . '?php $__blaze->popData(); ?>' . "\n";
        $output .= '<' . '?php unset($__resolved) ?>';

        return $output;
    }

}
