<?php

namespace Livewire\Blaze\Folder;

use Livewire\Blaze\Compiler\DirectiveMatcher;

/**
 * Analyzes if slots are used in a way that allows safe folding.
 * 
 * Only allows folding when slots are simply echoed without any transformation.
 * Valid patterns: {{ $slot }}, {!! $slot !!}
 * Invalid: Any other usage including concatenation, array values, method calls, etc.
 */
class SlotUsageAnalyzer
{
    public function __construct(
        protected DirectiveMatcher $matcher = new DirectiveMatcher,
    ) {}

    /**
     * Check if the component can be folded with the given slot names.
     *
     * @param string $source Component template source
     * @param array $slotNames Slot variable names (e.g., ['slot', 'header', 'footer'])
     * @return bool True if slots are only used in simple echo patterns
     */
    public function canFoldWithSlots(string $source, array $slotNames): bool
    {
        if (empty($slotNames)) {
            return true;
        }

        // Strip @unblaze blocks as they're handled separately
        $source = $this->stripUnblazeBlocks($source);

        foreach ($slotNames as $slotName) {
            // Check if slot is used in PHP blocks
            if ($this->isUsedInPhpBlock($source, $slotName)) {
                return false;
            }

            // Check if slot is used in Blade directives
            if ($this->isUsedInBladeDirective($source, $slotName)) {
                return false;
            }

            // Check if slot is ONLY used in simple echo patterns
            if (! $this->isOnlySimpleEcho($source, $slotName)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a slot is only used in simple echo patterns.
     * 
     * Valid: {{ $slot }}, {!! $slot !!}
     * Invalid: Any other usage
     */
    protected function isOnlySimpleEcho(string $source, string $slotName): bool
    {
        // Create a copy and remove all valid simple echo patterns
        $temp = $source;
        
        // Remove {{ $slot }} patterns
        $temp = preg_replace('/\{\{\s*\$' . preg_quote($slotName, '/') . '\s*\}\}/', '', $temp);
        
        // Remove {!! $slot !!} patterns  
        $temp = preg_replace('/\{!!\s*\$' . preg_quote($slotName, '/') . '\s*!!\}/', '', $temp);

        // If the slot variable still appears anywhere else, it's not just simple echo
        return ! $this->containsVariable($temp, $slotName);
    }

    /**
     * Check if a slot is used inside a PHP block.
     */
    protected function isUsedInPhpBlock(string $source, string $slotName): bool
    {
        // Check @php blocks
        if (preg_match_all('/@php\b(.*?)@endphp/s', $source, $matches)) {
            foreach ($matches[1] as $block) {
                if ($this->containsVariable($block, $slotName)) {
                    return true;
                }
            }
        }

        // Check standard PHP blocks
        if (preg_match_all('/<\?php(.*?)\?>/s', $source, $matches)) {
            foreach ($matches[1] as $block) {
                if ($this->containsVariable($block, $slotName)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if a slot is used in any Blade directive expression.
     */
    protected function isUsedInBladeDirective(string $source, string $slotName): bool
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

            if ($this->containsVariable($directive['expression'], $slotName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if content contains a variable reference.
     */
    protected function containsVariable(string $content, string $varName): bool
    {
        // Match $varName as a variable (word boundary after name)
        return (bool) preg_match('/\$' . preg_quote($varName, '/') . '\b/', $content);
    }

    /**
     * Strip @unblaze blocks from source.
     */
    protected function stripUnblazeBlocks(string $source): string
    {
        return preg_replace('/@unblaze.*?@endunblaze/s', '', $source);
    }
}