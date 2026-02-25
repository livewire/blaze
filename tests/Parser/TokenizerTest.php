<?php

use Livewire\Blaze\Parser\Tokenizer;
use Livewire\Blaze\Parser\Tokens\SlotCloseToken;
use Livewire\Blaze\Parser\Tokens\SlotOpenToken;
use Livewire\Blaze\Parser\Tokens\TagCloseToken;
use Livewire\Blaze\Parser\Tokens\TagOpenToken;
use Livewire\Blaze\Parser\Tokens\TagSelfCloseToken;

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

test('skips component inside PHP single-line comment', function () {
    $input = <<<'TPL'
<?php // <x-button /> ?>
<div>hello</div>
TPL;

    $tokens = app(Tokenizer::class)->tokenize($input);

    $types = array_map(fn($t) => class_basename($t), $tokens);

    expect($types)->each->toBe('TextToken');
});

test('skips component inside PHP block comment', function () {
    $input = <<<'TPL'
<?php /* <x-button /> */ ?>
<div>hello</div>
TPL;

    $tokens = app(Tokenizer::class)->tokenize($input);

    $types = array_map(fn($t) => class_basename($t), $tokens);

    expect($types)->each->toBe('TextToken');
});

test('skips component inside PHP string', function () {
    $input = <<<'TPL'
<?php echo "<x-button />"; ?>
<x-real />
TPL;

    $tokens = app(Tokenizer::class)->tokenize($input);

    $selfClose = array_filter($tokens, fn($t) => $t instanceof TagSelfCloseToken);
    expect(count($selfClose))->toBe(1);
    expect(array_values($selfClose)[0]->name)->toBe('real');
});

test('skips component inside unclosed PHP block at EOF', function () {
    $input = '<?php // <x-button />';

    $tokens = app(Tokenizer::class)->tokenize($input);

    $types = array_map(fn($t) => class_basename($t), $tokens);

    expect($types)->each->toBe('TextToken');
});

test('handles multiple PHP blocks with real component between them', function () {
    $input = <<<'TPL'
<?php // <x-hidden1 /> ?>
<x-visible />
<?php /* <x-hidden2 /> */ ?>
TPL;

    $tokens = app(Tokenizer::class)->tokenize($input);

    $selfClose = array_filter($tokens, fn($t) => $t instanceof TagSelfCloseToken);
    expect(count($selfClose))->toBe(1);
    expect(array_values($selfClose)[0]->name)->toBe('visible');
});
