<?php

namespace Livewire\Blaze\Parser;

/**
 * Represents a parsed attribute on a component or slot tag.
 */
class Attribute
{
    public function __construct(
        public string $name,
        public mixed $value,
        public string $propName,
        public bool $dynamic,
        public ?string $prefix = null,
        public string $quotes = '"',
        public bool $valueless = false,
    ) {
    }

    /**
     * Check if this attribute is PHP-bound (prefixed with : or :$).
     */
    public function bound(): bool
    {
        return $this->prefix === ':' || $this->prefix === ':$';
    }

    /**
     * Check if the attribute value can be resolved at compile time.
     */
    public function isStaticValue(): bool
    {
        return $this->dynamic === false || $this->hasConstantDynamicValue();
    }

    /**
     * Resolve the compile time value of this attribute.
     */
    public function getStaticValue(): mixed
    {
        if ($this->hasConstantDynamicValue()) {
            return $this->getConstantValue();
        }
            
        if ($this->dynamic) {
            throw new \LogicException("Cannot get static value of dynamic attribute '{$this->name}'.");
        }

        return $this->value;
    }

    /**
     * Check if this attribute is dynamic and has a constant value (true, false, or null).
     */
    protected function hasConstantDynamicValue(): bool
    {
        return $this->dynamic && in_array($this->value, ['true', 'false', 'null'], true);
    }

    /**
     * Resolve the constant value of this attribute (true, false, or null).
     */
    protected function getConstantValue()
    {
        return match ($this->value) {
            'true' => true,
            'false' => false,
            'null' => null,
            default => throw new \LogicException("Invalid constant value '{$this->value}'."),
        };
    }
}
