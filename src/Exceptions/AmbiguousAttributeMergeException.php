<?php

namespace Livewire\Blaze\Exceptions;

/**
 * Thrown while folding when differently escaped copies of a bound attribute meet in a merge,
 * which Blade merges into one or keeps apart depending on the value.
 */
class AmbiguousAttributeMergeException extends \RuntimeException
{
    public function __construct(
        protected string $attribute,
    ) {
        parent::__construct(
            "Cannot fold a component merging differently escaped copies of the bound attribute '{$attribute}': Blade merges them into one or keeps both depending on the value"
        );
    }

    /**
     * Get the name of the merged attribute.
     */
    public function getAttribute(): string
    {
        return $this->attribute;
    }
}
