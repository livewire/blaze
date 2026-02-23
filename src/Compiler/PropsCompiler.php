<?php

namespace Livewire\Blaze\Compiler;

use Livewire\Blaze\Exceptions\ArrayParserException;
use Livewire\Blaze\Exceptions\InvalidPropsDefinitionException;
use Illuminate\Support\Str;

/**
 * Compiles @props expressions into PHP code for extracting props from component data.
 */
class PropsCompiler
{
    /**
     * Compile a @props expression into PHP code.
     *
     * @throws InvalidPropsDefinitionException
     */
    public function compile(string $expression): string
    {
        try {
            $items = ArrayParser::parse($expression);
        } catch (ArrayParserException $e) {
            throw new InvalidPropsDefinitionException($e->expression, $e->getMessage());
        }

        if (empty($items)) {
            return '';
        }

        $output = '<?php'."\n";

        $output .= '$__defaults = ' . $expression . ';' . "\n";

        foreach ($items as $key => $value) {
            $name = is_int($key) ? $value : $key;
            $hasDefault = ! is_int($key);

            $kebab = Str::kebab($name);
            $hasKebabVariant = $kebab !== $name;

            $output .= ($hasKebabVariant
                ? $this->compileKebabAssignment($name, $kebab, $hasDefault)
                : $this->compileAssignment($name, $hasDefault));

            $output .= ($hasKebabVariant
                ? sprintf(' unset($attributes[\'%s\'], $attributes[\'%s\']);', $name, $kebab)
                : sprintf(' unset($attributes[\'%s\']);', $name)) . "\n";
        }

        $output .= 'unset($__defaults);' . "\n";

        $output .= '?>';

        return $output;
    }

    /**
     * Generate variable assignment for a prop without kebab variant.
     */
    protected function compileAssignment(string $name, bool $hasDefault): string
    {
        if ($hasDefault) {
            return sprintf('$%s ??= $attributes[\'%s\'] ?? $__defaults[\'%s\'];', $name, $name, $name);
        }

        return sprintf('$%s ??= $attributes[\'%s\'];', $name, $name);
    }

    /**
     * Generate variable assignment for a prop with kebab variant.
     */
    protected function compileKebabAssignment(string $name, string $kebab, bool $hasDefault): string
    {
        if ($hasDefault) {
            return sprintf('$%s ??= $attributes[\'%s\'] ?? $attributes[\'%s\'] ?? $__defaults[\'%s\'];', $name, $kebab, $name, $name);
        }

        return sprintf('$%s ??= $attributes[\'%s\'] ?? $attributes[\'%s\'];', $name, $kebab, $name);
    }
}
