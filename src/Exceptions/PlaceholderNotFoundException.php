<?php

namespace Livewire\Blaze\Exceptions;

class PlaceholderNotFoundException extends \RuntimeException
{
    public function __construct(
        protected string $placeholder,
        protected ?string $renderedSnippet = null
    ) {
        parent::__construct(
            "Attribute placeholder '{$placeholder}' not found in rendered output. The component template may not use this attribute."
        );
    }

    public function getPlaceholder(): string
    {
        return $this->placeholder;
    }

    public function getRenderedSnippet(): ?string
    {
        return $this->renderedSnippet;
    }
}