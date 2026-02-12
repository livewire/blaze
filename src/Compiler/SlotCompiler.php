<?php

namespace Livewire\Blaze\Compiler;

use Illuminate\Support\Str;
use Livewire\Blaze\Nodes\SlotNode;
use Closure;

/**
 * Compiles slot nodes into output buffering PHP code.
 */
class SlotCompiler
{
    public function __construct(
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
        $output = [];

        // Compile implicit default slot from loose content (non-SlotNode children)
        if (! $this->hasExplicitDefaultSlot($children)) {
            $output[] = $this->compileSlot('slot', $this->renderLooseContent($children), '[]', $slotsVariableName);
        }

        // Compile each named slot
        foreach ($children as $child) {
            if ($child instanceof SlotNode) {
                $output[] = $this->compileSlot(
                    $this->resolveSlotName($child),
                    $this->renderChildren($child->children),
                    $this->compileSlotAttributes($child),
                    $slotsVariableName,
                );
            }
        }

        return '<' . '?php ' . $slotsVariableName . ' = []; ?>' . "\n"
            . implode("\n", $output);
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
     * Render non-SlotNode children as the default slot content.
     *
     * @param array<Node> $children
     */
    protected function renderLooseContent(array $children): string
    {
        $content = '';
        $previousWasSlot = false;

        foreach ($children as $child) {
            if ($child instanceof SlotNode) {
                $previousWasSlot = true;
                continue;
            }

            $rendered = $child->render();

            // Laravel's slot compilation consumes the newline after </x-slot> and adds a leading space.
            // We match this by prepending a space and stripping any leading newline.
            if ($previousWasSlot) {
                $rendered = ' ' . preg_replace('/^\n/', '', $rendered);
            }

            $content .= $rendered;
            $previousWasSlot = false;
        }

        return $content;
    }

    /**
     * Compile a slot into ob_start/ob_get_clean code.
     */
    protected function compileSlot(string $name, string $content, string $attributes, string $slotsVariableName): string
    {
        return '<' . '?php ob_start(); ?>'
            . $content
            . '<' . '?php ' . $slotsVariableName . '[\'' . $name . '\'] = new \Illuminate\View\ComponentSlot(trim(ob_get_clean()), ' . $attributes . '); ?>';
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
