<?php

use Livewire\Blaze\Folder\Folder;
use Livewire\Blaze\Nodes\ComponentNode;
use Livewire\Blaze\Nodes\TextNode;
use Livewire\Blaze\Parser\Parser;

test('folds components with static attributes', function () {
    $input = '<x-input type="number" />';
    $node = app(Parser::class)->parse($input)[0];
    $folded = app(Folder::class)->fold($node);

    expect($folded)->toBeInstanceOf(TextNode::class);
});

test('folds components with slots', function () {
    $input = '
        <x-card>
            Body
            <x-slot:footer>
                Footer
            </x-slot:footer>
        </x-card>
    ';

    $node = app(Parser::class)->parse($input)[0];
    $folded = app(Folder::class)->fold($node);

    expect($folded)->toBeInstanceOf(TextNode::class);
});

test('folds components with dynamic non-prop attributes', function () {
    $input = '<x-input type="number" />';
    $output = '<input type="number" >';

    $node = app(Parser::class)->parse($input)[0];
    $folded = app(Folder::class)->fold($node);

    expect($folded)->toBeInstanceOf(TextNode::class);
    expect($folded->render())->toEqualCollapsingWhitespace($output);
});

test('aborts fold when dynamic prop attribute is passed', function () {
    $input = '<x-input :disabled="$disabled" />';
    
    $node = app(Parser::class)->parse($input)[0];
    $folded = app(Folder::class)->fold($node);

    expect($folded)->toBeInstanceOf(ComponentNode::class);
});

test('doesnt abort fold when dynamic non-prop attribute is passed', function () {
    $input = '<x-input :value="$value" />';
    $output = '<input type="text value="{{ $value }}" />';

    $node = app(Parser::class)->parse($input)[0];
    $folded = app(Folder::class)->fold($node);

    expect($folded)->toBeInstanceOf(TextNode::class);
    expect($folded->render())->toBe($output);
});

test('aborts fold when dynamic prop attribute marked as unsafe is passed', function () {
    $input = '<x-input :required="$required" />';
    
    $node = app(Parser::class)->parse($input)[0];
    $folded = app(Folder::class)->fold($node);

    expect($folded)->toBeInstanceOf(ComponentNode::class);
});

test('doesnt abort fold when dynamic prop attribute marked as safe is passed', function () {
    $input = '<x-input :type="$type" />';
    $output = '<input type="{{ $type }}" >';
    
    $node = app(Parser::class)->parse($input)[0];
    $folded = app(Folder::class)->fold($node);

    expect($folded)->toBeInstanceOf(TextNode::class);
    expect($folded->render())->toBe($output);
});

test('aborts fold when unsafe slot is passed', function () {
    $input = '<x-card><x-slot:footer>Footer</x-slot:footer></x-card>';
    
    $node = app(Parser::class)->parse($input)[0];
    $folded = app(Folder::class)->fold($node);

    expect($folded)->toBeInstanceOf(ComponentNode::class);
});