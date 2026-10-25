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
            return $source->directives->blaze('compile') ?? true;
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

        $output = '<' . '?php if (!function_exists(\'' . $functionName . '\')) { require $__blaze->compile(\'' . $source->path . '\'); } ?>' . "\n";

        if ($node->selfClosing) {
            $dataVariableName = '$__data' . $hash;
            [$dataArrayString, $attributesArrayString] = $this->compileAttributes($node, $dataVariableName);
            $output .= '<' . '?php ' . $dataVariableName . ' = ' . $dataArrayString . '; ?>' . "\n";
            $output .= '<' . '?php $__blaze->pushData(' . $dataVariableName . '); ?>' . "\n";
            $output .= '<' . '?php ' . $functionName . '($__blaze, ' . $dataVariableName . ', ' . $attributesArrayString . ', [], $__this ?? (isset($this) ? $this : null)); ?>' . "\n";
        } else {
            $slotsVariableName = '$__slots' . $hash;
            $attributesVariableName = '$__attrs' . $hash;
            $slotsStackName = '$__slotsStack' . $hash;
            $attrsStackName = '$__attrsStack' . $hash;
            [$dataArrayString, $attributesArrayString] = $this->compileAttributes($node, $attributesVariableName);
            $output .= '<' . '?php if (isset(' . $slotsVariableName . ')) { ' . $slotsStackName . '[] = ' . $slotsVariableName . '; } ?>' . "\n";
            $output .= '<' . '?php if (isset(' . $attributesVariableName . ')) { ' . $attrsStackName . '[] = ' . $attributesVariableName . '; } ?>' . "\n";
            $output .= '<' . '?php ' . $attributesVariableName . ' = ' . $dataArrayString . '; ?>' . "\n";
            $output .= '<' . '?php ' . $slotsVariableName . ' = []; ?>' . "\n";
            $output .= '<' . '?php $__blaze->pushData(' . $attributesVariableName . '); ?>' . "\n";
            $output .= $this->slotCompiler->compile($slotsVariableName, $node->children);
            $output .= '<' . '?php $__blaze->pushSlots(' . $slotsVariableName . '); ?>' . "\n";
            $output .= '<' . '?php ' . $functionName . '($__blaze, ' . $attributesVariableName . ', ' . $attributesArrayString . ', ' . $slotsVariableName . ', $__this ?? (isset($this) ? $this : null)); ?>' . "\n";
            $output .= '<' . '?php if (! empty(' . $slotsStackName . ')) { ' . $slotsVariableName . ' = array_pop(' . $slotsStackName . '); } ?>' . "\n";
            $output .= '<' . '?php if (! empty(' . $attrsStackName . ')) { ' . $attributesVariableName . ' = array_pop(' . $attrsStackName . '); } ?>' . "\n";
        }

        $output .= '<' . '?php $__blaze->popData(); ?>';

        return $output;
    }

    /**
     * Build the data and attributes array strings a component is called with, like Blade.
     *
     * Data has camelCased keys and raw bound values, and is read by props. Attributes have the
     * original keys and escaped bound values, and become the attribute bag. Dynamic attribute
     * values are read from the data variable, so their expressions are evaluated only once.
     * An attribute bag passed with :attributes is merged into the attributes.
     *
     * @return array{string, string} Tuple of [dataArrayString, attributesArrayString]
     */
    protected function compileAttributes(ComponentNode $node, string $dataVariableName): array
    {
        $data = [];
        $attributes = [];

        foreach ($node->attributes as $attr) {
            $data[] = $this->blade->compileAttribute($attr);

            if (! $attr->dynamic) {
                $attributes[] = $this->blade->compileAttribute($attr, escapeBound: true, originalKey: true);
            } elseif ($attr->bound()) {
                $attributes[] = "'{$attr->name}' => \\Illuminate\\View\\Compilers\\BladeCompiler::sanitizeComponentAttribute({$dataVariableName}['{$attr->propName}'])";
            } else {
                $attributes[] = "'{$attr->name}' => {$dataVariableName}['{$attr->propName}']";
            }
        }

        $attributesArrayString = '[' . implode(', ', $attributes) . ']';

        if (isset($node->attributes['attributes'])) {
            $attributesArrayString = '$__blaze->mergeAttributes(' . $attributesArrayString . ')';
        }

        return ['[' . implode(',', $data) . ']', $attributesArrayString];
    }

    /**
     * Compile a flux:delegate-component tag into dynamic resolution code.
     */
    protected function compileDelegateComponentTag(ComponentNode $node): string
    {
        $componentName = "'flux::' . " . $node->attributes['component']->value;
        $functionName = '(\'' . ($this->manager->isFolding() ? '__' : '_') . '\' . $__resolved)';
        
        $output = '<' . '?php $__resolved = $__blaze->resolve(' . $componentName . '); ?>' . "\n";
        $output .= '<' . '?php $__delegatedData = $__delegateData ?? $__env->getCurrentComponentData(); ?>' . "\n";
        $output .= '<' . '?php $__blaze->pushData($__delegatedData); ?>' . "\n";
        $output .= '<' . '?php if ($__resolved !== false): ?>' . "\n";

        if ($node->selfClosing) {
            $output .= '<' . '?php ' . $functionName . '($__blaze, $__delegatedData, $attributes->getAttributes(), $__blaze->mergedComponentSlots(), $__this ?? (isset($this) ? $this : null)); ?>' . "\n";
        } else {
            $hash = Utils::hash($componentName);
            $slotsVariableName = '$__slots' . $hash;
            $slotsStackName = '$__slotsStack' . $hash;
            $output .= '<' . '?php if (isset(' . $slotsVariableName . ')) { ' . $slotsStackName . '[] = ' . $slotsVariableName . '; } ?>' . "\n";
            $output .= '<' . '?php ' . $slotsVariableName . ' = []; ?>' . "\n";
            $output .= $this->slotCompiler->compile($slotsVariableName, $node->children);
            $output .= '<' . '?php ' . $slotsVariableName . ' = array_merge($__blaze->mergedComponentSlots(), ' . $slotsVariableName . '); ?>' . "\n";
            $output .= '<' . '?php ' . $functionName . '($__blaze, $__delegatedData, $attributes->getAttributes(), ' . $slotsVariableName . ', $__this ?? (isset($this) ? $this : null)); ?>' . "\n";
            $output .= '<' . '?php if (! empty(' . $slotsStackName . ')) { ' . $slotsVariableName . ' = array_pop(' . $slotsStackName . '); } ?>' . "\n";
        }

        $output .= '<' . '?php else: ?>' . "\n";
        $output .= $this->compileDelegateBladeFallback($node);
        $output .= '<' . '?php endif; ?>' . "\n";

        $output .= '<' . '?php $__blaze->popData(); ?>' . "\n";
        $output .= '<' . '?php unset($__resolved, $__delegatedData) ?>';

        return $output;
    }

    /**
     * Compile the Blade fallback for a delegate target that is not a Blaze component.
     *
     * Mirrors Flux's own delegate-component compilation, but inside a Blaze component passes the data
     * the component was called with, since Blaze components never set the view factory's current component data.
     */
    protected function compileDelegateBladeFallback(ComponentNode $node): string
    {
        $component = '(' . $node->attributes['component']->value . ')';
        $view = "(app()->version() >= 12 ? hash('xxh128', 'flux') : md5('flux')) . '::' . " . $component;

        $output = '<' . '?php if (! Flux::componentExists(' . $component . ')) throw new \Exception(\'Flux component [\' . ' . $component . ' . \'] does not exist.\'); ?>' . "\n";
        $output .= "@component('Illuminate\\View\\AnonymousComponent', 'flux::' . " . $component . ", ['view' => " . $view . ", 'data' => array_merge(\$__delegatedData, \$__blaze->mergedComponentSlots())])\n";
        $output .= '<' . '?php $component->withAttributes($attributes->getAttributes()); ?>' . "\n";

        if (! $node->selfClosing) {
            foreach ($node->children as $child) {
                $output .= $child->render();
            }

            $output .= "\n";
        }

        $output .= '@endComponentClass' . "\n";

        return $output;
    }

}
