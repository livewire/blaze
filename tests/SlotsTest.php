<?php

test('renders default slot content', function () {
    $result = blade(
        view: '<x-card>Hello World</x-card>',
        components: ['card' => '@blaze
<div class="card"><div class="card-body">{{ $slot }}</div></div>'],
    );

    expect($result)->toContain('<div class="card-body">Hello World</div>');
});

test('renders empty default slot for self-closing component', function () {
    $result = blade(
        view: '<x-card />',
        components: ['card' => '@blaze
<div class="card"><div class="card-body">{{ $slot }}</div></div>'],
    );

    expect($result)->toContain('<div class="card-body"></div>');
});

test('renders named slot', function ($view) {
    $result = blade(
        view: $view,
        components: ['card' => '@blaze
<div class="card"><div class="card-header">{{ $header ?? "" }}</div><div class="card-body">{{ $slot }}</div></div>'],
    );

    expect($result)->toContain('<div class="card-header">My Header</div>');
    expect($result)->toContain('<div class="card-body">Body</div>');
})->with([
    'short syntax'    => ['<x-card><x-slot:header>My Header</x-slot:header>Body</x-card>'],
    'standard syntax' => ['<x-card><x-slot name="header">My Header</x-slot>Body</x-card>'],
]);

test('renders multiple named slots', function () {
    $component = <<<'BLADE'
@blaze
<div class="card">
    @if(isset($header))
    <div class="card-header">{{ $header }}</div>
    @endif
    <div class="card-body">{{ $slot }}</div>
    @if(isset($footer))
    <div class="card-footer">{{ $footer }}</div>
    @endif
</div>
BLADE;

    $result = blade(
        view: '<x-card><x-slot:header>Header</x-slot:header><x-slot:footer>Footer</x-slot:footer>Body</x-card>',
        components: ['card' => $component],
    );

    expect($result)->toContain('<div class="card-header">Header</div>');
    expect($result)->toContain('<div class="card-body">Body</div>');
    expect($result)->toContain('<div class="card-footer">Footer</div>');
});

test('converts kebab-case slot names to camelCase', function () {
    $result = blade(
        view: '<x-test><x-slot:card-header>Header</x-slot:card-header></x-test>',
        components: ['test' => '@blaze
<div>{{ $cardHeader ?? "none" }}</div>'],
    );

    expect($result)->toContain('<div>Header</div>');
});

test('explicit default slot takes precedence over loose content', function () {
    $template = '@blaze
<div>{{ $slot }}</div>';

    $result = blade(
        view: '<x-test>Loose<x-slot:slot>Explicit</x-slot></x-test>',
        components: ['test' => $template],
    );

    expect($result)->toContain('<div>Explicit</div>');
    expect($result)->not->toContain('Loose');
});

test('passes attributes to named slots', function () {
    $result = blade(
        view: '<x-card><x-slot:header class="text-lg">Title</x-slot></x-card>',
        components: ['card' => '@blaze
<div class="{{ $header->attributes->get("class") }}">{{ $header }}</div>'],
    );

    expect($result)->toContain('class="text-lg"');
});

test('renders Blade expressions in slot content', function () {
    $result = blade(
        view: '<x-card>Count: {{ $count }}</x-card>',
        components: ['card' => '@blaze
<div class="card">{{ $slot }}</div>'],
        data: ['count' => 42],
    );

    expect($result)->toContain('Count: 42');
});

test('renders nested components in slots', function () {
    $result = blade(
        view: '<x-card><x-badge>New</x-badge> Item</x-card>',
        components: [
            'card' => '@blaze
<div class="card">{{ $slot }}</div>',
            'badge' => '@blaze
<span class="badge">{{ $slot }}</span>',
        ],
    );

    expect($result)->toContain('<span class="badge">New</span>');
    expect($result)->toContain('Item');
});

test('renders slots regardless of definition order', function () {
    $result = blade(
        view: '<x-card><x-slot:footer>Footer First</x-slot:footer>Body<x-slot:header>Header Last</x-slot:header></x-card>',
        components: ['card' => '@blaze
<div class="header">{{ $header }}</div>
<div class="body">{{ $slot }}</div>
<div class="footer">{{ $footer }}</div>'],
    );

    expect($result)->toContain('<div class="header">Header Last</div>');
    expect($result)->toContain('<div class="body">Body</div>');
    expect($result)->toContain('<div class="footer">Footer First</div>');
});

test('slot whitespace matches Laravel parity', function ($view) {
    $blazeResult = blade(
        view: $view,
        components: ['wrapper' => '@blaze
<div>{{ $slot }}</div>'],
    );

    $laravelResult = blade(
        view: $view,
        components: ['wrapper' => '<div>{{ $slot }}</div>'],
    );

    expect($blazeResult)->toBe($laravelResult);
})->with([
    'newline after slot'     => ["<x-wrapper>before<x-slot:named>content</x-slot>\nafter</x-wrapper>"],
    'newlines both sides'    => ["<x-wrapper>before\n<x-slot:named>content</x-slot>\nafter</x-wrapper>"],
    'indented content'       => ["<x-wrapper>\n    before\n    <x-slot:named>content</x-slot>\n    after\n</x-wrapper>"],
    'multiple slots'         => ["<x-wrapper>start\n<x-slot:one>first</x-slot>\nmiddle\n<x-slot:two>second</x-slot>\nend</x-wrapper>"],
    'real world card layout' => ["<x-wrapper>\n    <x-slot:header>\n        Card Header\n    </x-slot>\n\n    Card body content here.\n\n    <x-slot:footer>\n        Card Footer\n    </x-slot>\n</x-wrapper>"],
]);

test('works with both props and slots', function () {
    $result = blade(
        view: '<x-card title="Custom Title">Body content</x-card>',
        components: ['card' => '@blaze
@props(["title" => "Default Title"])
<div class="card"><div class="card-title">{{ $title }}</div><div class="card-body">{{ $slot }}</div></div>'],
    );

    expect($result)->toContain('<div class="card-title">Custom Title</div>');
    expect($result)->toContain('<div class="card-body">Body content</div>');
});
