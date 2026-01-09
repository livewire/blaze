<?php

use Livewire\Blaze\Exceptions\InvalidPropsDefinitionException;
use Livewire\Blaze\Compiler\PropsCompiler;

describe('basic compilation', function () {
    it('compiles empty props array to empty string', function () {
        $compiler = new PropsCompiler;

        $result = $compiler->compile('[]');

        expect($result)->toBe('');
    });

    it('compiles single prop with string default', function () {
        $compiler = new PropsCompiler;

        $result = $compiler->compile("['type' => 'button']");

        expect($result)->toBe(
            "\$__defaults = ['type' => 'button'];\n" .
            "\$type = \$__data['type'] ?? \$__defaults['type'];\n" .
            "unset(\$__data['type']);\n" .
            "unset(\$__defaults);\n"
        );
    });

    it('compiles single required prop (numeric key)', function () {
        $compiler = new PropsCompiler;

        $result = $compiler->compile("['label']");

        expect($result)->toBe(
            "\$__defaults = ['label'];\n" .
            "if (array_key_exists('label', \$__data)) { \$label = \$__data['label']; }\n" .
            "unset(\$__data['label']);\n" .
            "unset(\$__defaults);\n"
        );
    });

    it('compiles mixed props with defaults and required', function () {
        $compiler = new PropsCompiler;

        $result = $compiler->compile("['type' => 'button', 'required', 'disabled' => false]");

        expect($result)->toBe(
            "\$__defaults = ['type' => 'button', 'required', 'disabled' => false];\n" .
            "\$type = \$__data['type'] ?? \$__defaults['type'];\n" .
            "unset(\$__data['type']);\n" .
            "if (array_key_exists('required', \$__data)) { \$required = \$__data['required']; }\n" .
            "unset(\$__data['required']);\n" .
            "\$disabled = \$__data['disabled'] ?? \$__defaults['disabled'];\n" .
            "unset(\$__data['disabled']);\n" .
            "unset(\$__defaults);\n"
        );
    });

    it('handles trailing comma', function () {
        $compiler = new PropsCompiler;

        $result = $compiler->compile("['type' => 'button',]");

        expect($result)->toContain("\$type = \$__data['type'] ?? \$__defaults['type'];");
    });

    it('handles multiline array', function () {
        $compiler = new PropsCompiler;

        $result = $compiler->compile("[
            'title' => 'Default Title',
            'subtitle',
            'showFooter' => true,
        ]");

        expect($result)->toContain("\$title = \$__data['title'] ?? \$__defaults['title'];");
        expect($result)->toContain("if (array_key_exists('subtitle', \$__data)) { \$subtitle = \$__data['subtitle']; }");
        expect($result)->toContain("\$showFooter = \$__data['show-footer'] ?? \$__data['showFooter'] ?? \$__defaults['showFooter'];");
    });
});

describe('default value types', function () {
    it('compiles string defaults', function () {
        $compiler = new PropsCompiler;

        $result = $compiler->compile("['type' => 'button']");

        expect($result)->toContain("['type' => 'button']");
    });

    it('compiles boolean defaults', function () {
        $compiler = new PropsCompiler;

        $result = $compiler->compile("['enabled' => true]");

        expect($result)->toContain("['enabled' => true]");
    });

    it('compiles integer defaults', function () {
        $compiler = new PropsCompiler;

        $result = $compiler->compile("['count' => 42]");

        expect($result)->toContain("['count' => 42]");
    });

    it('compiles null defaults', function () {
        $compiler = new PropsCompiler;

        $result = $compiler->compile("['optional' => null]");

        expect($result)->toContain("['optional' => null]");
    });

    it('compiles array defaults', function () {
        $compiler = new PropsCompiler;

        $result = $compiler->compile("['items' => ['a', 'b', 'c']]");

        expect($result)->toContain("['items' => ['a', 'b', 'c']]");
    });

    it('compiles closure defaults', function () {
        $compiler = new PropsCompiler;

        $result = $compiler->compile("['callback' => fn() => 'default']");

        expect($result)->toContain("['callback' => fn() => 'default']");
    });

    it('compiles expression defaults', function () {
        $compiler = new PropsCompiler;

        $result = $compiler->compile("['timestamp' => now()]");

        expect($result)->toContain("['timestamp' => now()]");
    });
});

describe('kebab-case handling', function () {
    it('single word prop uses direct lookup', function () {
        $compiler = new PropsCompiler;

        $result = $compiler->compile("['type' => 'button']");

        expect($result)->toContain("\$type = \$__data['type'] ?? \$__defaults['type'];");
        expect($result)->not->toContain("'type-'");
    });

    it('camelCase prop checks kebab-case first', function () {
        $compiler = new PropsCompiler;

        $result = $compiler->compile("['backgroundColor' => 'white']");

        expect($result)->toContain("\$backgroundColor = \$__data['background-color'] ?? \$__data['backgroundColor'] ?? \$__defaults['backgroundColor'];");
    });

    it('unset includes both camelCase and kebab-case variants', function () {
        $compiler = new PropsCompiler;

        $result = $compiler->compile("['backgroundColor' => 'white']");

        expect($result)->toContain("unset(\$__data['backgroundColor'], \$__data['background-color'])");
    });

    it('required camelCase prop checks both variants', function () {
        $compiler = new PropsCompiler;

        $result = $compiler->compile("['firstName']");

        expect($result)->toContain("if (array_key_exists('first-name', \$__data)) { \$firstName = \$__data['first-name']; } elseif (array_key_exists('firstName', \$__data)) { \$firstName = \$__data['firstName']; }");
    });
});

describe('error handling', function () {
    it('throws on invalid PHP syntax', function () {
        $compiler = new PropsCompiler;

        expect(fn() => $compiler->compile("['type' =>]"))
            ->toThrow(InvalidPropsDefinitionException::class);
    });

    it('throws when expression is not an array', function () {
        $compiler = new PropsCompiler;

        expect(fn() => $compiler->compile("'not an array'"))
            ->toThrow(InvalidPropsDefinitionException::class, 'must be an array');
    });

    it('throws on function call as expression', function () {
        $compiler = new PropsCompiler;

        expect(fn() => $compiler->compile("getDefaults()"))
            ->toThrow(InvalidPropsDefinitionException::class, 'must be an array');
    });

    it('throws when required prop is not a string literal', function () {
        $compiler = new PropsCompiler;

        expect(fn() => $compiler->compile("[0, \$dynamicProp]"))
            ->toThrow(InvalidPropsDefinitionException::class, 'value must be a string literal');
    });

    it('includes expression in error message', function () {
        $compiler = new PropsCompiler;

        try {
            $compiler->compile("['type' =>]");
            $this->fail('Expected exception not thrown');
        } catch (InvalidPropsDefinitionException $e) {
            expect($e->getMessage())->toContain("['type' =>]");
        }
    });
});

describe('edge cases', function () {
    it('handles empty string default', function () {
        $compiler = new PropsCompiler;

        $result = $compiler->compile("['label' => '']");

        expect($result)->toContain("['label' => '']");
    });

    it('handles string with special characters', function () {
        $compiler = new PropsCompiler;

        $result = $compiler->compile("['message' => 'Hello, \"World\"!']");

        expect($result)->toContain("['message' => 'Hello, \"World\"!']");
    });

    it('handles very long prop names', function () {
        $compiler = new PropsCompiler;

        $result = $compiler->compile("['thisIsAReallyLongPropertyNameThatShouldStillWork' => true]");

        expect($result)->toContain('$thisIsAReallyLongPropertyNameThatShouldStillWork');
    });

    it('handles array syntax variations', function () {
        $compiler = new PropsCompiler;

        // Old array() syntax (still valid PHP)
        $result = $compiler->compile("array('type' => 'button')");

        expect($result)->toContain("\$type = \$__data['type'] ?? \$__defaults['type'];");
    });
});


