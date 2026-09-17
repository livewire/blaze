<?php

namespace Livewire\Blaze\Parser;

/**
 * Holds the set of recognized component tag prefixes (e.g. 'x-', 'flux:')
 * and their associated Blade view-namespace and slot-tag prefix.
 */
class PrefixRegistry
{
    protected array $prefixes = [
        'flux:' => ['namespace' => 'flux::', 'slot' => 'x-slot'],
        'x:' => ['namespace' => '', 'slot' => 'x-slot'],
        'x-' => ['namespace' => '', 'slot' => 'x-slot'],
    ];

    /**
     * Register (or overwrite) a component tag prefix.
     */
    public function register(string $prefix, string $namespace = '', string $slotPrefix = 'x-slot'): void
    {
        $this->prefixes[$prefix] = [
            'namespace' => $namespace,
            'slot' => $slotPrefix,
        ];
    }

    /**
     * Get all registered prefixes, keyed by prefix string.
     */
    public function all(): array
    {
        return $this->prefixes;
    }
}
