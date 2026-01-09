<?php

namespace Livewire\Blaze\Runtime;

use Illuminate\Support\Arr;
use Illuminate\View\AppendableAttributeValue;
use Illuminate\View\ComponentAttributeBag;

class BlazeAttributeBag extends ComponentAttributeBag
{
    /**
     * Create an attribute bag with sanitized values for safe HTML rendering.
     *
     * @param array $attributes All attributes passed to the component
     * @param array $boundKeys Keys of attributes that were bound (from PHP expressions)
     */
    public static function sanitized(array $attributes, array $boundKeys = []): static
    {
        foreach ($boundKeys as $key) {
            if (array_key_exists($key, $attributes)) {
                $attributes[$key] = \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($attributes[$key]);
            }
        }

        return new static($attributes);
    }

    /**
     * Merge additional attributes / values into the attribute bag.
     *
     * Optimized to avoid Collection usage for ~4x speedup.
     */
    public function merge(array $attributeDefaults = [], $escape = true): static
    {
        if ($escape) {
            foreach ($attributeDefaults as $key => $value) {
                if ($this->shouldEscapeAttributeValue($escape, $value)) {
                    $attributeDefaults[$key] = e($value);
                }
            }
        }

        $result = $attributeDefaults;

        // Resolve AppendableAttributeValue defaults that have no matching instance attribute
        foreach ($result as $key => $value) {
            if ($value instanceof AppendableAttributeValue && ! isset($this->attributes[$key])) {
                $result[$key] = $this->resolveAppendableAttributeDefault($attributeDefaults, $key, $escape);
            }
        }

        foreach ($this->attributes as $key => $value) {
            $isAppendable = $key === 'class' || $key === 'style' || (
                isset($attributeDefaults[$key]) &&
                $attributeDefaults[$key] instanceof AppendableAttributeValue
            );

            if ($isAppendable) {
                $default = $attributeDefaults[$key] ?? '';

                if ($default instanceof AppendableAttributeValue) {
                    $default = $this->resolveAppendableAttributeDefault($attributeDefaults, $key, $escape);
                }

                if ($key === 'style' && $value !== '') {
                    $value = rtrim($value, ';').';';
                }

                if ($default !== '' && $value !== '') {
                    $result[$key] = $default.' '.$value;
                } elseif ($value !== '') {
                    $result[$key] = $value;
                }
            } else {
                $result[$key] = $value;
            }
        }

        return new static($result);
    }

    /**
     * Conditionally merge classes into the attribute bag.
     *
     * Optimized to avoid Arr::toCssClasses overhead.
     */
    public function class($classList): static
    {
        $classes = $this->toCssClasses(Arr::wrap($classList));

        return $this->merge(['class' => $classes]);
    }

    /**
     * Conditionally merge styles into the attribute bag.
     *
     * Optimized to avoid Arr::toCssStyles overhead.
     */
    public function style($styleList): static
    {
        $styles = $this->toCssStyles((array) $styleList);

        return $this->merge(['style' => $styles]);
    }

    /**
     * Convert class list to CSS classes string.
     */
    protected function toCssClasses(array $classList): string
    {
        $classes = [];

        foreach ($classList as $class => $constraint) {
            if (is_numeric($class)) {
                $classes[] = $constraint;
            } elseif ($constraint) {
                $classes[] = $class;
            }
        }

        return implode(' ', $classes);
    }

    /**
     * Convert style list to CSS styles string.
     */
    protected function toCssStyles(array $styleList): string
    {
        $styles = [];

        foreach ($styleList as $style => $constraint) {
            if (is_numeric($style)) {
                $styles[] = rtrim($constraint, ';').';';
            } elseif ($constraint) {
                $styles[] = rtrim($style, ';').';';
            }
        }

        return implode(' ', $styles);
    }
}
