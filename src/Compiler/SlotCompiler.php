<?php

namespace Livewire\Blaze\Compiler;

use Illuminate\Support\Str;
use Livewire\Blaze\Parser\Nodes\SlotNode;
use Livewire\Blaze\Parser\Nodes\TextNode;
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

        $wrappedSlotInfo = $this->findDirectiveWrappedSlots($children);
        $wrappedSlotIndices = array_keys($wrappedSlotInfo);
        $excludeTextIndices = $this->findDirectiveWrappingTextIndices($children, $wrappedSlotIndices);

        if (! $this->hasExplicitDefaultSlot($children)) {
            $output[] = $this->compileSlot(
                'slot',
                $this->renderLooseContent($children, $wrappedSlotIndices, $excludeTextIndices),
                '[]',
                $slotsVariableName
            );
        }

        foreach ($children as $index => $child) {
            if ($child instanceof SlotNode) {
                if (isset($wrappedSlotInfo[$index])) {
                    [$phpCondition, $phpEnd] = $wrappedSlotInfo[$index];
                    $output[] = $phpCondition
                        . $this->compileSlot(
                            $this->resolveSlotName($child),
                            $this->renderChildren($child->children),
                            $this->compileSlotAttributes($child),
                            $slotsVariableName,
                        )
                        . $phpEnd;
                } else {
                    $output[] = $this->compileSlot(
                        $this->resolveSlotName($child),
                        $this->renderChildren($child->children),
                        $this->compileSlotAttributes($child),
                        $slotsVariableName,
                    );
                }
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
     * Find text node indices containing only wrapping directives for slots.
     *
     * @param array<int> $wrappedSlotIndices
     * @return array<int>
     */
    protected function findDirectiveWrappingTextIndices(array $children, array $wrappedSlotIndices): array
    {
        $excludeIndices = [];

        foreach ($wrappedSlotIndices as $slotIndex) {
            if ($slotIndex > 0 && $children[$slotIndex - 1] instanceof TextNode) {
                $excludeIndices[] = $slotIndex - 1;
            }

            if ($slotIndex < count($children) - 1 && $children[$slotIndex + 1] instanceof TextNode) {
                $nextText = $children[$slotIndex + 1]->render();
                if ($this->startsWithClosingDirective($nextText) && !$this->hasNonDirectiveContent($nextText)) {
                    $excludeIndices[] = $slotIndex + 1;
                }
            }
        }

        return $excludeIndices;
    }

    /**
     * Check if text starts with a closing directive.
     */
    protected function startsWithClosingDirective(string $text): bool
    {
        return (bool) preg_match('/^\s*@(?:endif|endforeach|endforelse|endunless|endwhile|endfor|endswitch)/', $text);
    }

    /**
     * Check if text contains non-directive content.
     */
    protected function hasNonDirectiveContent(string $text): bool
    {
        $remaining = preg_replace('/^\s*@(?:endif|endforeach|endforelse|endunless|endwhile|endfor|endswitch)/', '', $text);
        return trim($remaining) !== '';
    }

    /**
     * Render non-SlotNode children as the default slot content.
     *
     * @param array<Node> $children
     * @param array<int> $wrappedSlotIndices
     * @param array<int> $excludeTextIndices
     */
    protected function renderLooseContent(array $children, array $wrappedSlotIndices = [], array $excludeTextIndices = []): string
    {
        $content = '';
        $previousWasSlot = false;

        foreach ($children as $index => $child) {
            if ($child instanceof SlotNode) {
                $previousWasSlot = true;
                continue;
            }

            if (in_array($index, $excludeTextIndices)) {
                $previousWasSlot = false;
                continue;
            }

            if ($child instanceof TextNode) {
                $rendered = $child->render();

                if ($index > 0 && isset($children[$index - 1]) && $children[$index - 1] instanceof SlotNode) {
                    $rendered = preg_replace('/@(?:if|foreach|forelse|unless|while|for|switch)\s*\([^)]*\)\s*$/', '', $rendered);
                }

                if ($index > 0 && isset($children[$index - 1]) && $children[$index - 1] instanceof SlotNode) {
                    $rendered = preg_replace('/^\s*@(?:endif|endforeach|endforelse|endunless|endwhile|endfor|endswitch)\s*/', '', $rendered);
                }
            } else {
                $rendered = $child->render();
            }

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

    /**
     * Find SlotNodes wrapped in directives and extract PHP equivalents.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    protected function findDirectiveWrappedSlots(array $children): array
    {
        $wrappedSlots = [];

        foreach ($children as $index => $child) {
            if ($child instanceof SlotNode) {
                $openingDirective = null;
                $closingDirective = null;

                // Check if previous child is a text node that ENDS with an opening directive
                if ($index > 0) {
                    $prevChild = $children[$index - 1];
                    if ($prevChild instanceof TextNode) {
                        $prevText = $prevChild->render();
                        $openingDirective = $this->extractOpeningDirective($prevText);
                    }
                }

                // Check if next child is a text node that STARTS with a closing directive
                if ($index < count($children) - 1) {
                    $nextChild = $children[$index + 1];
                    if ($nextChild instanceof TextNode) {
                        $nextText = $nextChild->render();
                        $closingDirective = $this->extractClosingDirective($nextText);
                    }
                }

                // If we have both directives, convert them to PHP and store
                if ($openingDirective && $closingDirective) {
                    $phpStart = $this->convertBladeDirectiveToPhp($openingDirective);
                    $phpEnd = $this->convertBladeClosingDirectiveToPhp($closingDirective);
                    $wrappedSlots[$index] = [$phpStart, $phpEnd];
                }
            }
        }

        return $wrappedSlots;
    }

    /**
     * Extract opening directive from end of text.
     */
    protected function extractOpeningDirective(string $text): ?string
    {
        if (preg_match('/(@(?:if|foreach|forelse|unless|while|for|switch)\s*\([^)]*\))\s*$/', $text, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Extract closing directive from start of text.
     */
    protected function extractClosingDirective(string $text): ?string
    {
        if (preg_match('/^\s*(@(?:endif|endforeach|endforelse|endunless|endwhile|endfor|endswitch))/', $text, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Convert Blade directive to PHP code.
     */
    protected function convertBladeDirectiveToPhp(string $directive): string
    {
        return '<?php ' . preg_replace('/^@/', '', $directive) . ': ?>';
    }

    /**
     * Convert Blade closing directive to PHP code.
     */
    protected function convertBladeClosingDirectiveToPhp(string $directive): string
    {
        $phpDirective = substr($directive, 1);
        return '<?php ' . $phpDirective . '; ?>';
    }

}
