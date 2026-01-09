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
    public function __construct(
        protected ArrayParser $parser = new ArrayParser,
    ) {}

    /**
     * Compile a @props expression into PHP code.
     *
     * @throws InvalidPropsDefinitionException
     */
    public function compile(string $expression): string
    {
        try {
            $items = $this->parser->parse($expression);
        } catch (ArrayParserException $e) {
            throw new InvalidPropsDefinitionException($e->expression, $e->getMessage());
        }

        if (empty($items)) {
            return '';
        }

        $output = [];

        // Pre-evaluate defaults to prevent cross-prop references
        $output[] = '$__defaults = ' . $expression . ';';

        foreach ($items as $name => $default) {
            $kebab = Str::kebab($name);
            $hasKebabVariant = $kebab !== $name;

            $output[] = $hasKebabVariant
                ? $this->compileKebabAssignment($name, $kebab, $default)
                : $this->compileAssignment($name, $default);

            $output[] = $hasKebabVariant
                ? sprintf('unset($__data[\'%s\'], $__data[\'%s\']);', $name, $kebab)
                : sprintf('unset($__data[\'%s\']);', $name);
        }

        $output[] = 'unset($__defaults);';

        return implode("\n", $output) . "\n";
    }

    /**
     * Generate variable assignment for a prop without kebab variant.
     */
    protected function compileAssignment(string $name, ?string $default): string
    {
        if ($default !== null) {
            return sprintf('$%s ??= $__data[\'%s\'] ?? $__defaults[\'%s\'];', $name, $name, $name);
        }

        return sprintf('if (!isset($%s) && array_key_exists(\'%s\', $__data)) { $%s = $__data[\'%s\']; }', $name, $name, $name, $name);
    }

    /**
     * Generate variable assignment for a prop with kebab variant.
     */
    protected function compileKebabAssignment(string $name, string $kebab, ?string $default): string
    {
        if ($default !== null) {
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
