<?php

namespace Livewire\Blaze\Runtime;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\View\AppendableAttributeValue;
use Illuminate\View\ComponentAttributeBag;

class BlazeAttributeBag extends ComponentAttributeBag
{
    /**
     * Create an attribute bag with sanitized values for safe HTML rendering.
     *
     * @param  array  $attributes  All attributes passed to the component
     * @param  array  $boundKeys  Keys of attributes that were bound (from PHP expressions)
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

    /**
     * Filter the attributes, returning a bag of attributes that pass the filter.
     *
     * @param  callable  $callback
     * @return static
     */
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

    /**
     * Return a bag of attributes that have keys starting with the given value / pattern.
     *
     * @param  string|string[]  $needles
     * @return static
     */
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

    /**
     * Return a bag of attributes with keys that do not start with the given value / pattern.
     *
     * @param  string|string[]  $needles
     * @return static
     */
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
     * Render attributes as HTML string.
     *
     * When folding is active, wraps each attribute with fence markers
     * so dynamic attributes can be converted to conditional PHP during restore.
     */
    public function __toString()
    {
        $string = '';

        foreach ($this->attributes as $key => $value) {
            if ($value === false || is_null($value)) {
                continue;
            }

            if ($value === true) {
                // Match Laravel's behavior: x-data and wire:* get empty string, others get key name
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
