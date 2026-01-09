<?php

namespace Livewire\Blaze\Compiler;

use Livewire\Blaze\Exceptions\ArrayParserException;
use Livewire\Blaze\Exceptions\InvalidAwareDefinitionException;

/**
 * Compiles @aware expressions into PHP code for accessing parent component data.
 */
class AwareCompiler
{
    public function __construct(
        protected ArrayParser $parser = new ArrayParser,
    ) {}

    /**
     * Compile an @aware expression into PHP code.
     *
     * @throws InvalidAwareDefinitionException
     */
    public function compile(string $expression): string
    {
        try {
            $items = $this->parser->parse($expression);
        } catch (ArrayParserException $e) {
            throw new InvalidAwareDefinitionException($e->expression, $e->getMessage());
        }

        if (empty($items)) {
            return '';
        }

        $output = [];

        foreach ($items as $name => $default) {
            $output[] = $default !== null
                ? sprintf('$%s = $__blaze->getConsumableData(\'%s\', %s);', $name, $name, $default)
                : sprintf('$%s = $__blaze->getConsumableData(\'%s\');', $name, $name);
        }

        return implode("\n", $output) . "\n";
    }
}
