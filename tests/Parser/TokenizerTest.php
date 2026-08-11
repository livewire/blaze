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
        new DirectiveToken(name: 'csrf', original: $input)
    ]);
});

test('tokenizes directives with parameters', function () {
    $input = '@dd($foo)';

    $result = app(Tokenizer::class)->tokenize($input);

    expect($result)->toEqual([
        new DirectiveToken(name: 'dd', original: $input, expression: '$foo')
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

test('does not tokenize directives preceded by a word character', function () {
    $input = 'foo@if($bar)';

    $result = app(Tokenizer::class)->tokenize($input);

    expect($result)->toEqual([
        new TextToken(content: $input),
    ]);
});

test('does not skip invalid directive prefixes', function () {
    $input = '@- @if($foo)';

    $result = app(Tokenizer::class)->tokenize($input);

    expect($result)->toEqual([
        new TextToken(content: '@- '),
        new DirectiveToken(name: 'if', original: '@if($foo)', expression: '$foo'),
    ]);
});

test('handles directives with whitespace', function () {
    $input = '@if ($foo)';

    $result = app(Tokenizer::class)->tokenize($input);

    expect($result)->toEqual([
        new DirectiveToken(name: 'if', original: $input, expression: '$foo')
    ]);
});

test('handles namespaced directives', function () {
    $input = '@Foo::bar($foo)';

    $result = app(Tokenizer::class)->tokenize($input);

    expect($result)->toEqual([
        new DirectiveToken(name: 'Foo::bar', original: $input, expression: '$foo')
    ]);
});

test('handles directives with nested parentheses', function () {
    $input = "@include('foo', ['((a)' => '((a)'])";

    $result = app(Tokenizer::class)->tokenize($input);

    expect($result)->toEqual([
        new DirectiveToken(name: 'include', original: $input, expression: "'foo', ['((a)' => '((a)']"),
    ]);
});

test('handles unclosed directives', function () {
    $input = "@include('foo'";

    $result = app(Tokenizer::class)->tokenize($input);

    expect($result)->toEqual([
        new DirectiveToken(name: 'include', original: '@include'),
        new TextToken("('foo'")
    ]);
});

test('preserves directives whose parentheses cannot be repaired', function () {
    $input = '@if(foo(bar) trailing text';

    $result = app(Tokenizer::class)->tokenize($input);

    expect($result)->toEqual([
        new TextToken(content: $input),
    ]);
});

test('handles Laravel directive parenthesis cases', function (string $input, array $expected) {
    $result = app(Tokenizer::class)->tokenize($input);

    expect($result)->toEqual([
        new DirectiveToken(name: $expected[0], original: $input, expression: $expected[1] ?? null)
    ]);
})->with([
    'nested function calls' => [
        '@if (name(foo(bar)))',
        ['if', 'name(foo(bar))'],
    ],
    'closing parentheses in an each argument' => [
        "@each('foo', '(bar))')",
        ['each', "'foo', '(bar))'"],
    ],
    'opening parentheses in include data' => [
        "@include('foo', ['(('])",
        ['include', "'foo', ['((']"],
    ],
    'mixed parentheses in include data' => [
        "@include('foo', ['((a)' => '((a)'])",
        ['include', "'foo', ['((a)' => '((a)']"],
    ],
    'multiple closing parentheses in include data' => [
        '@includeUnless(true, \'foo\', ["foo" => "bar_))-))>"])',
        ['includeUnless', 'true, \'foo\', ["foo" => "bar_))-))>"]'],
    ],
    'mixed parentheses and a cast' => [
        '@includeFirst(["issue", "#45424)"], [(string) "foo()" => "bar(-(("])',
        ['includeFirst', '["issue", "#45424)"], [(string) "foo()" => "bar(-(("]'],
    ],
    'parentheses in a section name' => [
        "@section('issue#18317 :))')",
        ['section', "'issue#18317 :))'"],
    ],
]);

test('handles parantheses after a directive', function () {
    $input = '@unset ($unset)))';

    $result = app(Tokenizer::class)->tokenize($input);

    expect($result)->toEqual([
        new DirectiveToken(name: 'unset', original: '@unset ($unset)', expression: '$unset'),
        new TextToken(content: '))'),
    ]);
});
