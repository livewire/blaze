<?php

use Livewire\Blaze\Compiler\PropsCompiler;
use Livewire\Blaze\Exceptions\InvalidPropsDefinitionException;

beforeEach(function () {
    $this->compiler = new PropsCompiler;
});

test('compiles empty props to empty string', function () {
    expect($this->compiler->compile('[]'))->toBe('');
});

test('compiles prop with default value', function () {
    $result = $this->compiler->compile("['type' => 'button']");

    expect($result)->toContain("\$__defaults = ['type' => 'button'];");
    expect($result)->toContain("\$type ??= \$__data['type'] ?? \$__defaults['type'];");
    expect($result)->toContain("unset(\$__data['type']);");
});

test('compiles required prop (numeric key)', function () {
    $result = $this->compiler->compile("['label']");

    expect($result)->toContain("if (!isset(\$label) && array_key_exists('label', \$__data)) { \$label = \$__data['label']; }");
    expect($result)->toContain("unset(\$__data['label']);");
});

test('compiles camelCase prop with kebab-case lookup', function () {
    $result = $this->compiler->compile("['backgroundColor' => 'white']");

    expect($result)->toContain("\$backgroundColor ??= \$__data['background-color'] ?? \$__data['backgroundColor'] ?? \$__defaults['backgroundColor'];");
    expect($result)->toContain("unset(\$__data['backgroundColor'], \$__data['background-color'])");
});

test('compiles required camelCase prop with both variant lookups', function () {
    $result = $this->compiler->compile("['firstName']");

    expect($result)->toContain("if (!isset(\$firstName)) { if (array_key_exists('first-name', \$__data)) { \$firstName = \$__data['first-name']; } elseif (array_key_exists('firstName', \$__data)) { \$firstName = \$__data['firstName']; } }");
});

test('handles formatting variations', function ($input) {
    $result = $this->compiler->compile($input);

    expect($result)->toContain("\$type ??= \$__data['type'] ?? \$__defaults['type'];");
})->with([
    'trailing comma' => ["['type' => 'button',]"],
    'multiline'      => ["[\n    'type' => 'button',\n]"],
    'array() syntax' => ["array('type' => 'button')"],
]);

test('preserves default value types in output', function ($input) {
    $result = $this->compiler->compile($input);

    // The defaults line should contain the original expression verbatim
    expect($result)->toContain('$__defaults = ' . $input);
})->with([
    'string'  => ["['type' => 'button']"],
    'boolean' => ["['enabled' => true]"],
    'integer' => ["['count' => 42]"],
    'null'    => ["['optional' => null]"],
    'array'   => ["['items' => ['a', 'b', 'c']]"],
    'closure' => ["['callback' => fn() => 'default']"],
]);

test('throws on invalid definitions', function ($input, $expectedMessage) {
    expect(fn() => $this->compiler->compile($input))
        ->toThrow(InvalidPropsDefinitionException::class, $expectedMessage);
})->with([
    'invalid syntax'   => ["['type' =>]", "['type' =>]"],
    'not an array'     => ["'not an array'", 'must be an array'],
    'function call'    => ["getDefaults()", 'must be an array'],
    'non-string value' => ["[0, \$dynamicProp]", 'value must be a string literal'],
]);
