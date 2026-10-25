<?php

namespace Livewire\Blaze\Runtime;

use Illuminate\Support\Arr;
use Illuminate\View\AppendableAttributeValue;
use Illuminate\View\ComponentAttributeBag;

/**
 * Optimized ComponentAttributeBag replacement avoiding Collection overhead.
 */
class BlazeAttributeBag extends ComponentAttributeBag
{
    /**
     * Skip the parent setAttributes() check for nested ComponentAttributeBag.
     * In the Blaze path, a bag passed with :attributes is already merged
     * at the call site by BlazeRuntime::mergeAttributes().
     */
    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    /** {@inheritdoc} */
    public function merge(array $attributeDefaults = [], $escape = true): static
    {
        if ($escape) {
            foreach ($attributeDefaults as $key => $value) {
                // Inlined shouldEscapeAttributeValue() to avoid a method call per value.
                if (! is_object($value) && ! is_null($value) && ! is_bool($value)) {
                    $attributeDefaults[$key] = e($value);
                }
            }
        }

        $appendableAttributes = [];
        $nonAppendableAttributes = [];

        foreach ($this->attributes as $key => $value) {
            $defaultsValue = $attributeDefaults[$key] ?? '';

            if ($defaultsValue instanceof AppendableAttributeValue) {
                $defaultsValue = $this->resolveAppendableAttributeDefault($attributeDefaults, $key, $escape);
            } elseif ($key !== 'class' && $key !== 'style') {
                $nonAppendableAttributes[$key] = $value;

                continue;
            }

            if ($key === 'style') {
                $value = rtrim((string) $value, ';').';';

                if (is_string($defaultsValue) && $defaultsValue !== '') {
                    $defaultsValue = rtrim($defaultsValue, ';').';';
                }
            }

            if (! $defaultsValue) {
                $appendableAttributes[$key] = $value ?: '';
            } elseif (! $value || $value === $defaultsValue) {
                $appendableAttributes[$key] = $defaultsValue;
            } else {
                $appendableAttributes[$key] = $defaultsValue.' '.$value;
            }
        }

        return new static(array_merge($attributeDefaults, $appendableAttributes, $nonAppendableAttributes));
    }

    /** {@inheritdoc} */
    public function class($classList): static
    {
        $classes = $this->toCssClasses(Arr::wrap($classList));

        return $this->merge(['class' => $classes]);
    }

    /** {@inheritdoc} */
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

    /** {@inheritdoc} */
    public function filter($callback)
    {
        $filtered = [];
        foreach ($this->attributes as $key => $value) {
            if ($callback($value, $key)) {
                $filtered[$key] = $value;
            }
        }

        return new static($filtered);
    }

    /** {@inheritdoc} */
    public function whereStartsWith($needles)
    {
        $needles = (array) $needles;

        return $this->filter(function ($value, $key) use ($needles) {
            foreach ($needles as $needle) {
                if ($needle !== '' && strncmp($key, $needle, strlen($needle)) === 0) {
                    return true;
                }
            }

            return false;
        });
    }

    /** {@inheritdoc} */
    public function whereDoesntStartWith($needles)
    {
        $needles = (array) $needles;

        return $this->filter(function ($value, $key) use ($needles) {
            foreach ($needles as $needle) {
                if ($needle !== '' && strncmp($key, $needle, strlen($needle)) === 0) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * Render attributes as HTML, wrapping placeholders with fence markers for folding.
     */
    public function __toString()
    {
        $parts = [];

        foreach ($this->attributes as $key => $value) {
            if ($value === false || is_null($value)) {
                continue;
            }

            if ($value === true) {
                $value = $key === 'x-data' || str_starts_with($key, 'wire:') ? '' : $key;
            }

            if (str_starts_with($value, 'BLAZE_PLACEHOLDER_') && str_ends_with($value, '_')) {
                $parts[] = '[BLAZE_ATTR:'.$value.':'.$key.']';
            } else {
                $parts[] = $key.'="'.str_replace('"', '\\"', trim($value)).'"';
            }
        }

        return implode(' ', $parts);
    }
}
