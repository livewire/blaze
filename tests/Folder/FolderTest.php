<?php

use Livewire\Blaze\Config;
use Livewire\Blaze\Parser\Parser;
use Livewire\Blaze\Folder\Folder;
use Livewire\Blaze\Parser\Nodes\TextNode;
use Livewire\Blaze\Parser\Nodes\ComponentNode;
use Illuminate\Support\Facades\Artisan;
use Livewire\Blaze\Blaze;

test('folds components with static attributes', function () {
    $input = '<x-foldable.input disabled />';
    
    $node = app(Parser::class)->parse($input)[0];
    $folded = app(Folder::class)->fold($node);

    expect($folded)->toBeInstanceOf(TextNode::class);
});

test('does not fold components with dynamic prop attributes', function () {
    $input = '<x-foldable.input :disabled="$disabled" />';
    
    $node = app(Parser::class)->parse($input)[0];
    $folded = app(Folder::class)->fold($node);

    expect($folded)->toBeInstanceOf(ComponentNode::class);
});

test('folds components with dynamic non-prop attributes', function () {
    $input = '<x-foldable.input :value="$value" />';
    
    $node = app(Parser::class)->parse($input)[0];
    $folded = app(Folder::class)->fold($node);

    expect($folded)->toBeInstanceOf(TextNode::class);
});

test('folds components with dynamic prop attributes with boolean values', function ($value) {
    $input = '<x-foldable.input :disabled="' . $value . '" />';
    
    $node = app(Parser::class)->parse($input)[0];
    $folded = app(Folder::class)->fold($node);

    expect($folded)->toBeInstanceOf(TextNode::class);
})->with(['true', 'false']);

test('folds components with dynamic prop attributes with null value', function () {
    $input = '<x-foldable.input :disabled="null" />';
    
    $node = app(Parser::class)->parse($input)[0];
    $folded = app(Folder::class)->fold($node);

    expect($folded)->toBeInstanceOf(TextNode::class);
});

test('fold components with dynamic prop attributes marked as safe', function () {
    $input = '<x-foldable.input :type="$type" />';
    
    $node = app(Parser::class)->parse($input)[0];
    $folded = app(Folder::class)->fold($node);

    expect($folded)->toBeInstanceOf(TextNode::class);
});

test('does not fold components with dynamic non-prop attributes marked as unsafe', function () {
    $input = '<x-foldable.input :required="$required" />';
    
    $node = app(Parser::class)->parse($input)[0];
    $folded = app(Folder::class)->fold($node);

    expect($folded)->toBeInstanceOf(ComponentNode::class);
});

test('does not fold components with attribute spread', function () {
    $input = '<x-foldable.input :attributes="$attributes" />';
    
    $node = app(Parser::class)->parse($input)[0];
    $folded = app(Folder::class)->fold($node);

    expect($folded)->toBeInstanceOf(ComponentNode::class);
});

test('folds components with slots', function () {
    $input = '<x-foldable.card><x-slot:header>Header</x-slot:header>Body</x-foldable.card>';
    
    $node = app(Parser::class)->parse($input)[0];
    $folded = app(Folder::class)->fold($node);

    expect($folded)->toBeInstanceOf(TextNode::class);
});

test('does not fold components with slots marked as unsafe', function () {
    $input = '<x-foldable.card>Body<x-slot:footer>Footer</x-slot:footer></x-foldable.card>';
    
    $node = app(Parser::class)->parse($input)[0];
    $folded = app(Folder::class)->fold($node);

    expect($folded)->toBeInstanceOf(ComponentNode::class);
});

test('folds components with dynamic prop attributes with safe wildcard', function () {
    $input = '<x-foldable.input-safe :type="$type" :disabled="$disabled" />';
    
    $node = app(Parser::class)->parse($input)[0];
    $folded = app(Folder::class)->fold($node);

    expect($folded)->toBeInstanceOf(TextNode::class);
});

test('does not fold components with dynamic non-prop attributes with unsafe wildcard', function () {
    $input = '<x-foldable.input-unsafe :required="$required" />';
    
    $node = app(Parser::class)->parse($input)[0];
    $folded = app(Folder::class)->fold($node);

    expect($folded)->toBeInstanceOf(ComponentNode::class);
});

test('folds components without static attributes with unsafe wildcard', function () {
    $input = '<x-foldable.input-unsafe type="number" required />';
    
    $node = app(Parser::class)->parse($input)[0];
    $folded = app(Folder::class)->fold($node);

    expect($folded)->toBeInstanceOf(TextNode::class);
});

test('does not fold components with slots with unsafe wildcard', function () {
    $input = '<x-foldable.card-unsafe>Body</x-foldable.card-unsafe>';
    
    $node = app(Parser::class)->parse($input)[0];
    $folded = app(Folder::class)->fold($node);

    expect($folded)->toBeInstanceOf(ComponentNode::class);
});

test('does not fold components with default slot with unsafe slot keyword', function () {
    $input = '<x-foldable.card-unsafe-slot>Body</x-foldable.card-unsafe-slot>';
    
    $node = app(Parser::class)->parse($input)[0];
    $folded = app(Folder::class)->fold($node);

    expect($folded)->toBeInstanceOf(ComponentNode::class);
});

test('does not fold components with dynamic non-prop attributes with unsafe attributes keyword', function () {
    $input = '<x-foldable.input-unsafe-attributes :required="$required" />';
    
    $node = app(Parser::class)->parse($input)[0];
    $folded = app(Folder::class)->fold($node);

    expect($folded)->toBeInstanceOf(ComponentNode::class);
});

test('folds components with static non-prop attributes with unsafe attributes keyword', function () {
    $input = '<x-foldable.input-unsafe-attributes required />';
    
    $node = app(Parser::class)->parse($input)[0];
    $folded = app(Folder::class)->fold($node);

    expect($folded)->toBeInstanceOf(TextNode::class);
});

test('does not fold components with dynamic slot attributes', function () {
    $input = '<x-foldable.card><x-slot:header :class="$class">Header</x-slot:header>Body</x-foldable.card>';

    $node = app(Parser::class)->parse($input)[0];
    $folded = app(Folder::class)->fold($node);

    expect($folded)->toBeInstanceOf(ComponentNode::class);
});

test('does not fold components with no blaze directive', function () {
    $input = '<x-foldable.input-no-blaze />';
    
    $node = app(Parser::class)->parse($input)[0];
    $folded = app(Folder::class)->fold($node);

    expect($folded)->toBeInstanceOf(ComponentNode::class);
});

test('folds components with no blaze directive if defined in config', function () {
    $input = '<x-foldable.input-no-blaze />';

    app(Config::class)->add(fixture_path('components/foldable'), fold: true);
    
    $node = app(Parser::class)->parse($input)[0];
    $folded = app(Folder::class)->fold($node);

    expect($folded)->toBeInstanceOf(TextNode::class);
});

test('folds components with blaze directive even if disabled in config', function () {
    $input = '<x-foldable.input />';

    app(Config::class)->add(fixture_path('components/foldable'), fold: false);
    
    $node = app(Parser::class)->parse($input)[0];
    $folded = app(Folder::class)->fold($node);

    expect($folded)->toBeInstanceOf(TextNode::class);
});