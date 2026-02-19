<?php

use Livewire\Blaze\Parser\Tokenizer;
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

test('handles whitespace in tags', function () {
    $input = '<x- button ></x-button >'; // This is valid Blade syntax...

    $result = app(Tokenizer::class)->tokenize($input);

    expect($result)->toEqual([
        new TagOpenToken(name: 'button', prefix: 'x-'),
        new TagCloseToken(name: 'button', prefix: 'x-'),
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