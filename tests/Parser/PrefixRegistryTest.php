<?php

use Livewire\Blaze\Parser\PrefixRegistry;

test('has default built-in prefixes', function () {
    $registry = new PrefixRegistry;

    expect($registry->all())->toBe([
        'flux:' => ['namespace' => 'flux::', 'slot' => 'x-slot'],
        'x:' => ['namespace' => '', 'slot' => 'x-slot'],
        'x-' => ['namespace' => '', 'slot' => 'x-slot'],
    ]);
});

test('registers a new prefix', function () {
    $registry = new PrefixRegistry;

    $registry->register('tk:', 'tk::');

    expect($registry->all())->toHaveKey('tk:', ['namespace' => 'tk::', 'slot' => 'x-slot']);
});

test('registers a new prefix with a custom slot prefix', function () {
    $registry = new PrefixRegistry;

    $registry->register('tk:', 'tk::', 'tk-slot');

    expect($registry->all())->toHaveKey('tk:', ['namespace' => 'tk::', 'slot' => 'tk-slot']);
});

test('overwrites an existing prefix without duplicating it', function () {
    $registry = new PrefixRegistry;

    $registry->register('x-', 'custom::');

    expect($registry->all())->toHaveCount(3)
        ->and($registry->all())->toHaveKey('x-', ['namespace' => 'custom::', 'slot' => 'x-slot']);
});
