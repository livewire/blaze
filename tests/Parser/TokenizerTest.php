<?php

use Livewire\Blaze\Parser\Tokenizer;
use Livewire\Blaze\Parser\Tokens\SlotCloseToken;
use Livewire\Blaze\Parser\Tokens\SlotOpenToken;
use Livewire\Blaze\Parser\Tokens\TagCloseToken;
use Livewire\Blaze\Parser\Tokens\TagOpenToken;
use Livewire\Blaze\Parser\Tokens\TagSelfCloseToken;
use Livewire\Blaze\Parser\Tokens\TextToken;

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