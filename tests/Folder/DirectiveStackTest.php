<?php

use Livewire\Blaze\Support\DirectiveStack;

test('tracks open directive structures', function () {
    $stack = DirectiveStack::make();

    expect($stack->open())->toBeFalse();

    $stack->add('if');
    $stack->add('foreach');

    expect($stack->open())->toBeTrue();

    $stack->add('endforeach');

    expect($stack->open())->toBeTrue();

    $stack->add('endif');

    expect($stack->open())->toBeFalse();
});

test('supports alternative closing directives', function (string $closer) {
    $stack = DirectiveStack::make();

    $stack->add('section');
    $stack->add($closer);

    expect($stack->open())->toBeFalse();
})->with(['show', 'append', 'overwrite', 'stop', 'endsection']);

test('supports endif for condition-like directives', function (string $opener) {
    $stack = DirectiveStack::make();

    $stack->add($opener);
    $stack->add('endif');

    expect($stack->open())->toBeFalse();
})->with(['unless', 'isset', 'empty', 'auth', 'guest', 'env', 'production', 'once', 'can', 'cannot', 'canany', 'hassection', 'hasstack', 'sectionmissing']);

test('only closes compatible structures', function () {
    $stack = DirectiveStack::make();

    $stack->add('if');
    $stack->add('endforeach');

    expect($stack->open())->toBeTrue();
});

test('closes intervening unmatched structures with a matching outer structure', function () {
    $stack = DirectiveStack::make();

    $stack->add('if');
    $stack->add('foreach');
    $stack->add('endif');

    expect($stack->open())->toBeFalse();
});

test('tracks livewire directive structures', function (string $opener, string $closer) {
    $stack = DirectiveStack::make();

    $stack->add($opener);

    expect($stack->open())->toBeTrue();

    $stack->add($closer);

    expect($stack->open())->toBeFalse();
})->with([
    ['script', 'endscript'],
    ['assets', 'endassets'],
    ['island', 'endisland'],
    ['teleport', 'endteleport'],
    ['persist', 'endpersist'],
    ['placeholder', 'endplaceholder'],
]);

test('tracks custom Blade conditions', function (string $opener, string $closer) {
    $stack = DirectiveStack::make(['custom']);

    $stack->add($opener);

    expect($stack->open())->toBeTrue();

    $stack->add($closer);

    expect($stack->open())->toBeFalse();
})->with([
    ['custom', 'endcustom'],
    ['custom', 'endif'],
    ['unlesscustom', 'endcustom'],
    ['unlesscustom', 'endif'],
]);
