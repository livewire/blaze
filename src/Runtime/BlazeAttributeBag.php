<?php

namespace Livewire\Blaze\Runtime;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\View\AppendableAttributeValue;
use Illuminate\View\ComponentAttributeBag;

/**
 * Optimized ComponentAttributeBag replacement avoiding Collection overhead.
 */
class BlazeAttributeBag extends ComponentAttributeBag
{
    /**
     * Create an attribute bag with bound values sanitized for safe HTML rendering.
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

    /** {@inheritdoc} */
    public function merge(array $attributeDefaults = [], $escape = true): static
    {
        if ($escape) {
            foreach ($attributeDefaults as $key => $value) {
                if ($this->shouldEscapeAttributeValue($escape, $value)) {
                    $attributeDefaults[$key] = e($value);
                }
            }
        }

        $appendableAttributes = [];
        $nonAppendableAttributes = [];

        foreach ($this->attributes as $key => $value) {
            $isAppendable = $key === 'class' || $key === 'style' || (
                isset($attributeDefaults[$key]) &&
                $attributeDefaults[$key] instanceof AppendableAttributeValue
            );

            if ($isAppendable) {
                $appendableAttributes[$key] = $value;
            } else {
                $nonAppendableAttributes[$key] = $value;
            }
        }

        $attributes = [];

        foreach ($appendableAttributes as $key => $value) {
            $defaultsValue = isset($attributeDefaults[$key]) && $attributeDefaults[$key] instanceof AppendableAttributeValue
                ? $this->resolveAppendableAttributeDefault($attributeDefaults, $key, $escape)
                : ($attributeDefaults[$key] ?? '');

            if ($key === 'style') {
                $value = rtrim((string) $value, ';').';';
            }

            $merged = [];
            foreach ([$defaultsValue, $value] as $part) {
                if (! $part) {
                    continue;
                }

                if (! in_array($part, $merged)) {
                    $merged[] = $part;
                }
            }

            $attributes[$key] = implode(' ', $merged);
        }

        foreach ($nonAppendableAttributes as $key => $value) {
            $attributes[$key] = $value;
        }

        return new static(array_merge($attributeDefaults, $attributes));
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
        $string = '';

        foreach ($this->attributes as $key => $value) {
            if ($value === false || is_null($value)) {
                continue;
            }

            if ($value === true) {
                $value = $key === 'x-data' || str_starts_with($key, 'wire:') ? '' : $key;
            }

            $attr = $key.'="'.str_replace('"', '\\"', trim($value)).'"';

            if (Str::match('/^BLAZE_PLACEHOLDER_[A-Z0-9]+$/', $value)) {
                $string .= ' [BLAZE_ATTR:'.$value.']';
            } else {
                $string .= ' '.$attr;
            }
        }

        return trim($string);
    }
}
