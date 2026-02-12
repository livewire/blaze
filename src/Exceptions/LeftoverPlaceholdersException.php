<?php

namespace Livewire\Blaze\Exceptions;

/**
 * Thrown when Blaze placeholders remain in output after folding, indicating a replacement failure.
 */
class LeftoverPlaceholdersException extends \RuntimeException
{
    public function __construct(
        protected string $componentName,
        protected string $leftoverSummary,
        protected ?string $renderedSnippet = null
    ) {
        parent::__construct(
            "Leftover Blaze placeholders detected after folding component '{$componentName}': {$leftoverSummary}"
        );
    }

    /**
     * Get the name of the component that had leftover placeholders.
     */
    public function getComponentName(): string
    {
        return $this->componentName;
    }

    /**
     * Get a summary of the leftover placeholders found.
     */
    public function getLeftoverSummary(): string
    {
        return $this->leftoverSummary;
    }

    /**
     * Get a snippet of the rendered output containing placeholders.
     */
    public function getRenderedSnippet(): ?string
    {
        return $this->renderedSnippet;
    }
}