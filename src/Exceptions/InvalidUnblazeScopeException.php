<?php

namespace Livewire\Blaze\Exceptions;

/**
 * Thrown when the @unblaze scope of a folded component holds a dynamic value it can't restore.
 */
class InvalidUnblazeScopeException extends \RuntimeException
{
    public function __construct(
        protected string $componentName,
        protected string $attributeName,
    ) {
        parent::__construct(
            "Component '{$componentName}' can't be folded because its @unblaze scope transforms the dynamic value of '{$attributeName}'. "
            . "Pass it to the scope unchanged and transform it inside the @unblaze block."
        );
    }

    /**
     * Get the name of the component that couldn't be folded.
     */
    public function getComponentName(): string
    {
        return $this->componentName;
    }

    /**
     * Get the name of the attribute or slot that was transformed.
     */
    public function getAttributeName(): string
    {
        return $this->attributeName;
    }
}
