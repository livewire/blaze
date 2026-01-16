<?php

namespace Livewire\Blaze\Folder;

/**
 * Result of analyzing an $attributes method chain.
 *
 * Tracks which props are forwarded through the chain and how they're renamed.
 */
class AttributeBagAnalysisResult
{
    public function __construct(
        public ?array $included,  // null = all props; array = only these props
        public array $excluded,   // Props filtered by except()
        public array $renamed,    // parentProp => childProp mappings from merge()
    ) {}

    /**
     * Given parent's undeclared dynamic props, return forwarded mappings.
     *
     * @param array $parentProps Props available in parent's $attributes bag
     * @return array<string, string> parentProp => childProp
     */
    public function resolveForwarding(array $parentProps): array
    {
        $forwarded = [];

        // Determine which props pass through the chain
        $propsToForward = $this->included !== null
            ? array_intersect($parentProps, $this->included)
            : $parentProps;

        // Remove excluded props
        $propsToForward = array_diff($propsToForward, $this->excluded);

        // Map each forwarded prop to its child name (same name if not renamed)
        foreach ($propsToForward as $prop) {
            $forwarded[$prop] = $prop;
        }

        // Apply renamings from merge() - these are additional props being forwarded
        // e.g., ->merge(['class' => $variant]) means $variant forwards as 'class'
        foreach ($this->renamed as $parentProp => $childProp) {
            $forwarded[$parentProp] = $childProp;
        }

        return $forwarded;
    }
}
