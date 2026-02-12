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

        $output = '';

        $output .= '$__defaults = ' . $expression . ';' . "\n";

        foreach ($items as $key => $value) {
            $name = is_int($key) ? $value : $key;
            $hasDefault = ! is_int($key);

            $kebab = Str::kebab($name);
            $hasKebabVariant = $kebab !== $name;

            $output .= ($hasKebabVariant
                ? $this->compileKebabAssignment($name, $kebab, $hasDefault)
                : $this->compileAssignment($name, $hasDefault)) . "\n";

            $output .= ($hasKebabVariant
                ? sprintf('unset($__data[\'%s\'], $__data[\'%s\']);', $name, $kebab)
                : sprintf('unset($__data[\'%s\']);', $name)) . "\n";
        }

        $output .= 'unset($__defaults);' . "\n";

        return $output;
    }

    /**
     * Generate variable assignment for a prop without kebab variant.
     */
    protected function compileAssignment(string $name, bool $hasDefault): string
    {
        if ($hasDefault) {
            return sprintf('$%s ??= $__data[\'%s\'] ?? $__defaults[\'%s\'];', $name, $name, $name);
        }

        return sprintf('if (!isset($%s) && array_key_exists(\'%s\', $__data)) { $%s = $__data[\'%s\']; }', $name, $name, $name, $name);
    }

    /**
     * Generate variable assignment for a prop with kebab variant.
     */
    protected function compileKebabAssignment(string $name, string $kebab, bool $hasDefault): string
    {
        if ($hasDefault) {
            return sprintf('$%s ??= $__data[\'%s\'] ?? $__data[\'%s\'] ?? $__defaults[\'%s\'];', $name, $kebab, $name, $name);
        }

        // Required props: check if key exists (allows explicit null)
        return sprintf(
            'if (!isset($%s)) { if (array_key_exists(\'%s\', $__data)) { $%s = $__data[\'%s\']; } elseif (array_key_exists(\'%s\', $__data)) { $%s = $__data[\'%s\']; } }',
            $name,
            $kebab,
            $name,
            $kebab,
            $name,
            $name,
            $name
        );
    }
}
