<?php

namespace Livewire\Blaze\Compiler;

use Livewire\Blaze\Exceptions\ArrayParserException;
use Livewire\Blaze\Exceptions\InvalidAwareDefinitionException;
use Illuminate\Support\Str;

/**
 * Compiles @aware expressions into PHP code for accessing parent component data.
 */
class AwareCompiler
{
    /**
     * Compile an @aware expression into PHP code.
     *
     * @throws InvalidAwareDefinitionException
     */
    public function compile(string $expression): string
    {
        try {
            $items = ArrayParser::parse($expression);
        } catch (ArrayParserException $e) {
            throw new InvalidAwareDefinitionException($e->expression, $e->getMessage());
        }

        if (empty($items)) {
            return '';
        }

        $output = '<?php'."\n";

        $output .= '$__awareDefaults = ' . $expression . ';' . "\n";

        foreach ($items as $key => $value) {
            $name = is_int($key) ? $value : $key;
            $arguments = is_int($key) ? '\''.$name.'\'' : '\''.$name.'\', $__awareDefaults[\''.$name.'\']';
            $output .= '$'.$name.' = $__view ? $__env->getConsumableComponentData('.$arguments.') : $__blaze->getConsumableData('.$arguments.');' . "\n";
        }

        $output .= 'unset($__awareDefaults);' . "\n";

        $output .= '?>';

        return $output;
    }
}
