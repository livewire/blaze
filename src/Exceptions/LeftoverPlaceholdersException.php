<?php

namespace Livewire\Blaze\Exceptions;

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

    public function getComponentName(): string
    {
        return $this->componentName;
    }

    public function getLeftoverSummary(): string
    {
        return $this->leftoverSummary;
    }

    public function getRenderedSnippet(): ?string
    {
        return $this->renderedSnippet;
    }
}