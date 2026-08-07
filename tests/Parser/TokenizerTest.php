<?php

use Livewire\Blaze\Parser\Tokenizer;
use Livewire\Blaze\Parser\Tokens\TagOpenToken;
use Livewire\Blaze\Parser\Tokens\TextToken;
use Livewire\Blaze\Parser\Tokens\DirectiveToken;
use Livewire\Blaze\Parser\Tokens\TagCloseToken;
use Livewire\Blaze\Parser\Tokens\PhpBlockToken;

test('tokenizes php directive blocks', function () {
    $input = '@php $i = 0; @endphp';

    $result = app(Tokenizer::class)->tokenize($input);

    expect($result)->toEqual([
        new PhpBlockToken($input)
    ]);
});

test('tokenizes tags', function () {
    $input = '<x-button type="button"></x-button>';

    $result = app(Tokenizer::class)->tokenize($input);

    expect($result)->toEqual([
        new TagOpenToken(prefix: 'x-', name: 'button', attributes: ' type="button"', original: '<x-button type="button">', selfClosing: false),
        new TagCloseToken(prefix: 'x-', name: 'button', original: '</x-button>'),
    ]);
});

test('tokenizes self-closing tags', function () {
    $input = '<x-button type="button" />';

    $result = app(Tokenizer::class)->tokenize($input);

    expect($result)->toEqual([
        new TagOpenToken(prefix: 'x-', name: 'button', attributes: ' type="button" ', original: '<x-button type="button" />', selfClosing: true),
    ]);
});

test('tokenizes flux tags', function () {
    $input = '<flux:button type="button"></flux:button>';

    $result = app(Tokenizer::class)->tokenize($input);

    expect($result)->toEqual([
        new TagOpenToken(prefix: 'flux:', name: 'button', attributes: ' type="button"', original: '<flux:button type="button">', selfClosing: false),
        new TagCloseToken(prefix: 'flux:', name: 'button', original: '</flux:button>'),
    ]);
});

test('only matches tags at the current position', function () {
    $input = '< invalid <x-button></ invalid </x-button>';

    expect(app(Tokenizer::class)->tokenize($input))->toEqual([
        new TextToken('< invalid '),
        new TagOpenToken(prefix: 'x-', name: 'button', attributes: '', original: '<x-button>', selfClosing: false),
        new TextToken('</ invalid '),
        new TagCloseToken(prefix: 'x-', name: 'button', original: '</x-button>'),
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

test('tokenizes php blocks', function () {
    $input = '<x-button><?php // <x-button /> ?></x-button>';

    $result = app(Tokenizer::class)->tokenize($input);

    expect($result)->toEqual([
        new TagOpenToken(prefix: 'x-', name: 'button', attributes: '', original: '<x-button>', selfClosing: false),
        new PhpBlockToken(content: '<?php // <x-button /> ?>'),
        new TagCloseToken(prefix: 'x-', name: 'button', original: '</x-button>'),
    ]);
});

test('handles unclosed php blocks', function () {
    $input = '<?php // <x-button />';

    $result = app(Tokenizer::class)->tokenize($input);

    expect($result)->toEqual([
        new PhpBlockToken(content: '<?php // <x-button />'),
    ]);
});

test('handles Blade php blocks', function () {
    $input = '<x-button> @php $value = "<x-button />"; @endphp </x-button>';

    expect(app(Tokenizer::class)->tokenize($input))->toEqual([
        new TagOpenToken(prefix: 'x-', name: 'button', attributes: '', original: '<x-button>', selfClosing: false),
        new TextToken(' '),
        new PhpBlockToken(content: '@php $value = "<x-button />"; @endphp'),
        new TextToken(' '),
        new TagCloseToken(prefix: 'x-', name: 'button', original: '</x-button>'),
    ]);
});

test('handles unclosed Blade php blocks', function () {
    $input = '@php $value = "<x-button />";';

    expect(app(Tokenizer::class)->tokenize($input))->toEqual([
        new DirectiveToken(name: 'php', original: '@php '), // <-- TODO: weird whitespace
        new TextToken(content: '$value = "'),
        new TagOpenToken(
            prefix: 'x-', name: 'button',
            attributes: ' ', // <-- TODO: weird whitespace
            original: '<x-button />',
            selfClosing: true,
        ),
        new TextToken(content: '";'),
    ]);
});

test('handles php blocks inside tags', function () {
    $input = '<x-button <?php echo "disabled"; ?>>';

    $result = app(Tokenizer::class)->tokenize($input);

    expect($result)->toEqual([
        new TextToken(content: '<x-button '),
        new PhpBlockToken(content: '<?php echo "disabled"; ?>'),
        new TextToken(content: '>'),
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

test('handles comments', function () {
    $input = '{{-- Comment --}}<x-button />';

    $result = app(Tokenizer::class)->tokenize($input);

    expect($result)->toEqual([
        new TagOpenToken(prefix: 'x-', name: 'button', attributes: ' ', original: '<x-button />', selfClosing: true),
    ]);
});