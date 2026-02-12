<?php

use Livewire\Blaze\Compiler\AwareCompiler;
use Livewire\Blaze\Exceptions\InvalidAwareDefinitionException;

describe('basic compilation', function () {
    it('compiles single variable with default', function () {
        $compiler = new AwareCompiler;

        $result = $compiler->compile("['color' => 'gray']");

        expect($result)->toBe(
            "\$__awareDefaults = ['color' => 'gray'];\n" .
            "\$color = \$__blaze->getConsumableData('color', \$__awareDefaults['color']);\n" .
            "unset(\$__awareDefaults);\n"
        );
    });

    it('compiles single variable without default', function () {
        $compiler = new AwareCompiler;

        $result = $compiler->compile("['color']");

        expect($result)->toBe(
            "\$__awareDefaults = ['color'];\n" .
            "\$color = \$__blaze->getConsumableData('color');\n" .
            "unset(\$__awareDefaults);\n"
        );
    });

    it('compiles multiple variables with defaults', function () {
        $compiler = new AwareCompiler;

        $result = $compiler->compile("['color' => 'gray', 'size' => 'md']");

        expect($result)->toBe(
            "\$__awareDefaults = ['color' => 'gray', 'size' => 'md'];\n" .
            "\$color = \$__blaze->getConsumableData('color', \$__awareDefaults['color']);\n" .
            "\$size = \$__blaze->getConsumableData('size', \$__awareDefaults['size']);\n" .
            "unset(\$__awareDefaults);\n"
        );
    });

    it('compiles mixed default and no-default variables', function () {
        $compiler = new AwareCompiler;

        $result = $compiler->compile("['color' => 'gray', 'size', 'disabled' => false]");

        expect($result)->toBe(
            "\$__awareDefaults = ['color' => 'gray', 'size', 'disabled' => false];\n" .
            "\$color = \$__blaze->getConsumableData('color', \$__awareDefaults['color']);\n" .
            "\$size = \$__blaze->getConsumableData('size');\n" .
            "\$disabled = \$__blaze->getConsumableData('disabled', \$__awareDefaults['disabled']);\n" .
            "unset(\$__awareDefaults);\n"
        );
    });

    it('compiles empty array to empty string', function () {
        $compiler = new AwareCompiler;

        $result = $compiler->compile('[]');

        expect($result)->toBe('');
    });
});

describe('default value types', function () {
    it('compiles string defaults', function () {
        $compiler = new AwareCompiler;

        $result = $compiler->compile("['variant' => 'primary']");

        expect($result)->toContain("['variant' => 'primary']");
    });

    it('compiles boolean defaults', function () {
        $compiler = new AwareCompiler;

        $result = $compiler->compile("['disabled' => true, 'hidden' => false]");

        expect($result)->toContain('true');
        expect($result)->toContain('false');
    });

    it('compiles integer defaults', function () {
        $compiler = new AwareCompiler;

        $result = $compiler->compile("['count' => 42]");

        expect($result)->toContain('42');
    });

    it('compiles null defaults', function () {
        $compiler = new AwareCompiler;

        $result = $compiler->compile("['nullable' => null]");

        expect($result)->toContain('null');
    });

    it('compiles array defaults', function () {
        $compiler = new AwareCompiler;

        $result = $compiler->compile("['items' => ['a', 'b', 'c']]");

        expect($result)->toContain("['a', 'b', 'c']");
    });

    it('compiles closure defaults', function () {
        $compiler = new AwareCompiler;

        $result = $compiler->compile("['callback' => fn() => 'value']");

        expect($result)->toContain("fn() => 'value'");
    });

    it('compiles expression defaults', function () {
        $compiler = new AwareCompiler;

        $result = $compiler->compile("['timestamp' => now()]");

        expect($result)->toContain('now()');
    });
});

describe('error handling', function () {
    it('throws on non-array expression', function () {
        $compiler = new AwareCompiler;

        expect(fn() => $compiler->compile("'not an array'"))
            ->toThrow(InvalidAwareDefinitionException::class, 'must be an array');
    });

    it('throws on invalid syntax', function () {
        $compiler = new AwareCompiler;

        expect(fn() => $compiler->compile('[invalid syntax'))
            ->toThrow(InvalidAwareDefinitionException::class);
    });

    it('throws on non-string key', function () {
        $compiler = new AwareCompiler;

        expect(fn() => $compiler->compile('[123 => "value"]'))
            ->toThrow(InvalidAwareDefinitionException::class, 'key must be a string literal');
    });

    it('throws on non-string value for numeric key', function () {
        $compiler = new AwareCompiler;

        expect(fn() => $compiler->compile('[123]'))
            ->toThrow(InvalidAwareDefinitionException::class, 'value must be a string literal');
    });

    it('includes expression in error message', function () {
        $compiler = new AwareCompiler;

        try {
            $compiler->compile("'invalid'");
            expect(false)->toBeTrue();
        } catch (InvalidAwareDefinitionException $e) {
            expect($e->getMessage())->toContain('invalid');
        }
    });
});

describe('edge cases', function () {
    it('handles trailing comma', function () {
        $compiler = new AwareCompiler;

        $result = $compiler->compile("['color' => 'gray',]");

        expect($result)->toContain("\$color = \$__blaze->getConsumableData('color', \$__awareDefaults['color']);");
    });

    it('handles multiline array', function () {
        $compiler = new AwareCompiler;

        $result = $compiler->compile("[
            'color' => 'gray',
            'size' => 'md',
        ]");

        expect($result)->toContain("\$color = \$__blaze->getConsumableData('color', \$__awareDefaults['color'])");
        expect($result)->toContain("\$size = \$__blaze->getConsumableData('size', \$__awareDefaults['size'])");
    });

    it('handles empty string default', function () {
        $compiler = new AwareCompiler;

        $result = $compiler->compile("['label' => '']");

        expect($result)->toContain("''");
    });

    it('handles special characters in string defaults', function () {
        $compiler = new AwareCompiler;

        $result = $compiler->compile("['message' => 'Hello \"World\"!']");

        expect($result)->toContain('Hello');
    });
});
