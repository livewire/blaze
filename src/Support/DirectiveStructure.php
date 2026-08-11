<?php

namespace Livewire\Blaze\Support;

class DirectiveStructure
{
    public function __construct(
        public array $openers,
        public array $closers = [],
    ) {
    }

    /**
     * Determine whether the directive opens this structure.
     */
    public function opensWith(string $name): bool
    {
        return in_array($name, $this->openers, true);
    }

    /**
     * Determine whether the directive closes this structure.
     */
    public function closesWith(string $name): bool
    {
        if (in_array($name, $this->closers, true)) {
            return true;
        }

        foreach ($this->openers as $opener) {
            if ($name === 'end'.$opener) {
                return true;
            }
        }

        return false;
    }
}
