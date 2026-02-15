<?php

use Livewire\Blaze\Compiler\AwareCompiler;
use Livewire\Blaze\Exceptions\InvalidAwareDefinitionException;

beforeEach(function () {
    $this->compiler = new AwareCompiler;
});

test('compiles empty array to empty string', function () {
    expect($this->compiler->compile('[]'))->toBe('');
});

test('compiles variable with default', function () {
    $result = $this->compiler->compile("['color' => 'gray']");

    expect($result)->toBe(
        "\$__awareDefaults = ['color' => 'gray'];\n" .
        "\$color = \$__blaze->getConsumableData('color', \$__awareDefaults['color']);\n" .
        "unset(\$__data['color']);\n" .
        "unset(\$__awareDefaults);\n"
    );
});

test('compiles variable without default', function () {
    $result = $this->compiler->compile("['color']");

    expect($result)->toContain("\$color = \$__blaze->getConsumableData('color');");
});

test('compiles multiple variables', function () {
    $result = $this->compiler->compile("['color' => 'gray', 'size' => 'md']");

    expect($result)->toContain("\$color = \$__blaze->getConsumableData('color', \$__awareDefaults['color']);");
    expect($result)->toContain("\$size = \$__blaze->getConsumableData('size', \$__awareDefaults['size']);");
});

test('compiles mixed default and no-default variables', function () {
    $result = $this->compiler->compile("['color' => 'gray', 'size', 'disabled' => false]");

    expect($result)->toContain("getConsumableData('color', \$__awareDefaults['color'])");
    expect($result)->toContain("getConsumableData('size')");
    expect($result)->toContain("getConsumableData('disabled', \$__awareDefaults['disabled'])");
});

test('handles formatting variations', function ($input) {
    $result = $this->compiler->compile($input);

    expect($result)->toContain("getConsumableData('color', \$__awareDefaults['color'])");
})->with([
    'trailing comma' => ["['color' => 'gray',]"],
    'multiline'      => ["[\n    'color' => 'gray',\n    'size' => 'md',\n]"],
]);

test('throws on invalid definitions', function ($input, $expectedMessage) {
    expect(fn() => $this->compiler->compile($input))
        ->toThrow(InvalidAwareDefinitionException::class, $expectedMessage);
})->with([
    'not an array'     => ["'not an array'", 'must be an array'],
    'invalid syntax'   => ['[invalid syntax', ''],
    'non-string key'   => ['[123 => "value"]', 'key must be a string literal'],
    'non-string value' => ['[123]', 'value must be a string literal'],
]);
