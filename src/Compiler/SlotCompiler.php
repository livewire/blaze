<?php

namespace Livewire\Blaze\Compiler;

use Closure;
use Illuminate\Support\Str;
use Livewire\Blaze\BlazeManager;
use Livewire\Blaze\Parser\Nodes\SlotNode;

/**
 * Compiles slot nodes into output buffering PHP code.
 */
class SlotCompiler
{
    public function __construct(
        protected BlazeManager $manager,
        protected Closure $getAttributesArrayString
    ) {
    }

    /**
     * Compile component children into slot assignments.
     *
     * @param array<Node> $children
     */
    public function compile(string $slotsVariableName, array $children): string
    {
        $output = '';
        $hasExplicitDefault = $this->hasExplicitDefaultSlot($children);

        if (! $hasExplicitDefault) {
            $output .= '<' . '?php ob_start(); ?>';
        }

        foreach ($children as $child) {
            if ($child instanceof SlotNode) {
                $output .= ' ' . $this->compileSlot(
                    $this->resolveSlotName($child),
                    $this->renderChildren($child->children),
                    $this->compileSlotAttributes($child),
                    $slotsVariableName,
                );
            } else {
                $output .= $child->render();
            }
        }

        if (! $hasExplicitDefault) {
            $contentHandler = $this->manager->isFolding()
                ? '$__blaze->processPassthroughContent(\'trim\', trim(ob_get_clean()))'
                : 'trim(ob_get_clean())';

            $output .= '<' . '?php ' . $slotsVariableName . '[\'slot\'] = new \Illuminate\View\ComponentSlot(' . $contentHandler . ', []); ?>' . "\n";
        }

        return $output;
    }

    /**
     * Check if children contain an explicit default slot (<x-slot:slot> or <x-slot name="slot">).
     *
     * @param array<Node> $children
     */
    protected function hasExplicitDefaultSlot(array $children): bool
    {
        foreach ($children as $child) {
            if ($child instanceof SlotNode && $this->resolveSlotName($child) === 'slot') {
                return true;
            }
        }

        return false;
    }

    /**
     * Compile a slot into ob_start/ob_get_clean code.
     */
    protected function compileSlot(string $name, string $content, string $attributes, string $slotsVariableName): string
    {
        $contentHandler = $this->manager->isFolding() ? '$__blaze->processPassthroughContent(\'trim\', trim(ob_get_clean()))' : 'trim(ob_get_clean())';
        
        return '<' . '?php ob_start(); ?>'
            . $content
            . '<' . '?php ' . $slotsVariableName . '[\'' . $name . '\'] = new \Illuminate\View\ComponentSlot(' . $contentHandler . ', ' . $attributes . '); ?>';
    }

    /**
     * Compile slot attributes to PHP array syntax.
     */
    protected function compileSlotAttributes(SlotNode $slot): string
    {
        $attributeString = $slot->attributeString;

        // For standard syntax, name="..." is the slot name, not an attribute
        if ($slot->slotStyle === 'standard') {
            $attributeString = preg_replace('/(?:^|\s)name\s*=\s*(?:"[^"]*"|\'[^\']*\')\s*/', ' ', $attributeString);
        }

        $attributeString = trim($attributeString);

        if (empty($attributeString)) {
            return '[]';
        }

        return ($this->getAttributesArrayString)($attributeString);
    }

    /**
     * Resolve slot name from SlotNode, handling kebab-case conversion.
     */
    protected function resolveSlotName(SlotNode $slot): string
    {
        $name = $slot->name;

        // Standard syntax: <x-slot name="header">
        if (empty($name)) {
            $name = preg_match('/(?:^|\s)name\s*=\s*["\']([^"\']+)["\']/', $slot->attributeString, $matches)
                ? $matches[1]
                : 'slot';
        }

        // Short syntax converts kebab-case to camelCase
        if ($slot->slotStyle === 'short' && Str::contains($name, '-')) {
            return Str::camel($name);
        }

        return $name;
    }

    /**
     * Render child nodes to string.
     *
     * @param array<Node> $children
     */
    protected function renderChildren(array $children): string
    {
        return implode('', array_map(fn ($child) => $child->render(), $children));
    }
}
