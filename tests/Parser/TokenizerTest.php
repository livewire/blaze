<?php

use Livewire\Blaze\Parser\Tokenizer;
use Livewire\Blaze\Parser\Tokens\SlotCloseToken;
use Livewire\Blaze\Parser\Tokens\SlotOpenToken;
use Livewire\Blaze\Parser\Tokens\TagCloseToken;
use Livewire\Blaze\Parser\Tokens\TagOpenToken;
use Livewire\Blaze\Parser\Tokens\TagSelfCloseToken;
use Livewire\Blaze\Parser\Tokens\TextToken;
use Livewire\Blaze\Parser\Tokens\DirectiveToken;

test('tokenizes tags', function () {
    $input = '<x-button type="button"></x-button>';

    $result = app(Tokenizer::class)->tokenize($input);

    expect($result)->toEqual([
        new TagOpenToken(name: 'button', prefix: 'x-', attributes: ['type="button"']),
        new TagCloseToken(name: 'button', prefix: 'x-'),
    ]);
});

test('tokenizes self-closing tags', function () {
    $input = '<x-button type="button" />';

    $result = app(Tokenizer::class)->tokenize($input);

    expect($result)->toEqual([
        new TagSelfCloseToken(name: 'button', prefix: 'x-', attributes: ['type="button"']),
    ]);
});

test('tokenizes default slots', function () {
    $input = '<x-slot></x-slot>';

    $result = app(Tokenizer::class)->tokenize($input);

    expect($result)->toEqual([
        new SlotOpenToken(prefix: 'x-slot'),
        new SlotCloseToken(prefix: 'x-'),
    ]);
});

test('tokenizes standard slots', function () {
    $input = '<x-slot name="header"></x-slot>';

    $result = app(Tokenizer::class)->tokenize($input);

    expect($result)->toEqual([
        new SlotOpenToken(name: 'header', prefix: 'x-slot'),
        new SlotCloseToken(prefix: 'x-'),
    ]);
});

test('tokenizes short slots', function () {
    $input = '<x-slot:header class="p-2"></x-slot:header>';

    $result = app(Tokenizer::class)->tokenize($input);

    expect($result)->toEqual([
        new SlotOpenToken(name: 'header', slotStyle: 'short', prefix: 'x-slot', attributes: ['class="p-2"']),
        new SlotCloseToken(name: 'header', prefix: 'x-'),
    ]);
});

test('tokenizes directives without parameters', function () {
    $input = '@csrf';

    $result = app(Tokenizer::class)->tokenize($input);

    expect($result)->toEqual([
        new DirectiveToken(name: 'csrf')
    ]);
});

test('tokenizes directives with parameters', function () {
    $input = '@dd($foo)';

    $result = app(Tokenizer::class)->tokenize($input);

    expect($result)->toEqual([
        new DirectiveToken(name: 'dd', arguments: '$foo')
    ]);
});

test('handles whitespace in tags', function () {
    $input = '< x-button ></ x-button >'; // This is valid Blade syntax...

    $result = app(Tokenizer::class)->tokenize($input);

    expect($result)->toEqual([
        new TagOpenToken(name: 'button', prefix: 'x-'),
        new TagCloseToken(name: 'button', prefix: 'x-'),
    ]);
});

test('handles whitespace in slot tags', function () {
    $input = '< x-slot:header ></ x-slot >';  // This is valid Blade syntax...

    $result = app(Tokenizer::class)->tokenize($input);

    expect($result)->toEqual([
        new SlotOpenToken(name: 'header', slotStyle: 'short', prefix: 'x-slot'),
        new SlotCloseToken(),
    ]);
});

test('handles whitespace in short slot tags', function () {
    $input = '< x-slot:header ></ x-slot:header >'; // This is valid Blade syntax...

    $result = app(Tokenizer::class)->tokenize($input);

    expect($result)->toEqual([
        new SlotOpenToken(name: 'header', slotStyle: 'short', prefix: 'x-slot'),
        new SlotCloseToken(name: 'header'),
    ]);
});

test('handles attributes with angled brackets', function () {
    $input = '<x-button :data="[\'foo\' => \'bar\']" :callback="fn () => 0">';

    $result = app(Tokenizer::class)->tokenize($input);

    expect($result)->toEqual([
        new TagOpenToken(
            name: 'button',
            prefix: 'x-',
            attributes: [
                ':data="[\'foo\' => \'bar\']"',
                ':callback="fn () => 0"',
            ],
        ),
    ]);
});

test('handles php blocks', function () {
    $input = '<x-button><?php // <x-button /> ?></x-button>';

    $result = app(Tokenizer::class)->tokenize($input);

    expect($result)->toEqual([
        new TagOpenToken(name: 'button', prefix: 'x-'),
        new TextToken(content: '<?php // <x-button /> ?>'),
        new TagCloseToken(name: 'button', prefix: 'x-'),
    ]);
});

test('handles unclosed php blocks', function () {
    $input = '<?php // <x-button />';

    $result = app(Tokenizer::class)->tokenize($input);

    expect($result)->toEqual([
        new TextToken(content: '<?php // <x-button />'),
    ]);
});

test('handles php blocks inside tags', function () {
    $input = '<x-button <?php echo \'disabled\'; ?>>';

    $result = app(Tokenizer::class)->tokenize($input);

    expect($result)->toEqual([
        new TextToken(content: '<x-button <?php echo \'disabled\'; ?>>'),
    ]);
});

test('handles escaped directives', function () {
    $input = '@@csrf';

    $result = app(Tokenizer::class)->tokenize($input);

    expect($result)->toEqual([
        new TextToken(content: '@@csrf')
    ]);
});

test('handles directives with whitespace', function () {
    $input = '@if ($foo)';

    $result = app(Tokenizer::class)->tokenize($input);

    expect($result)->toEqual([
        new DirectiveToken(name: 'if', arguments: '$foo')
    ]);
});

test('handles namespaced directives', function () {
    $input = '@Foo::bar($foo)';

    $result = app(Tokenizer::class)->tokenize($input);

    expect($result)->toEqual([
        new DirectiveToken(name: 'Foo::bar', arguments: '$foo')
    ]);
});

test('handles directives with nested parentheses', function () {
    $input = "@include('foo', ['((a)' => '((a)'])";

    $result = app(Tokenizer::class)->tokenize($input);

    expect($result)->toEqual([
        new DirectiveToken(name: 'include', arguments: "'foo', ['((a)' => '((a)']")
    ]);
});

test('handles unclosed directives', function () {
    $input = "@include('foo'";

    $result = app(Tokenizer::class)->tokenize($input);

    expect($result)->toEqual([
        new DirectiveToken(name: 'include')
    ]);
});

test('handles Laravel directive parenthesis cases', function (string $input, array $expected) {
    $result = app(Tokenizer::class)->tokenize($input);

    expect($result)->toEqual($expected);
})->with([
    'nested function calls' => [
        '@if (name(foo(bar)))',
        [new DirectiveToken(name: 'if', arguments: 'name(foo(bar))')],
    ],
    'closing parentheses in an each argument' => [
        "@each('foo', '(bar))')",
        [new DirectiveToken(name: 'each', arguments: "'foo', '(bar))'")],
    ],
    'opening parentheses in include data' => [
        "@include('foo', ['(('])",
        [new DirectiveToken(name: 'include', arguments: "'foo', ['((']")],
    ],
    'mixed parentheses in include data' => [
        "@include('foo', ['((a)' => '((a)'])",
        [new DirectiveToken(name: 'include', arguments: "'foo', ['((a)' => '((a)']")],
    ],
    'multiple closing parentheses in include data' => [
        '@includeUnless(true, \'foo\', ["foo" => "bar_))-))>"])',
        [new DirectiveToken(name: 'includeUnless', arguments: 'true, \'foo\', ["foo" => "bar_))-))>"]')],
    ],
    'mixed parentheses and a cast' => [
        '@includeFirst(["issue", "#45424)"], [(string) "foo()" => "bar(-(("])',
        [new DirectiveToken(name: 'includeFirst', arguments: '["issue", "#45424)"], [(string) "foo()" => "bar(-(("]')],
    ],
    'parentheses in a section name' => [
        "@section('issue#18317 :))')",
        [new DirectiveToken(name: 'section', arguments: "'issue#18317 :))'")],
    ],
    'parentheses after a directive' => [
        '@unset ($unset)))',
        [
            new DirectiveToken(name: 'unset', arguments: '$unset'),
            new TextToken(content: '))'),
        ],
    ],
]);