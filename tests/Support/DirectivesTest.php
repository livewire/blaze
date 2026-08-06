<?php

use Livewire\Blaze\Parser\Nodes\DirectiveNode;
use Livewire\Blaze\Parser\Parser;
use Livewire\Blaze\Support\Directives;

test('parses arrays', function () {
    $directives = new Directives(
        app(Parser::class)->parse('@aware([\'name\' => null, \'value\'])')
    );

    expect($directives->array('aware'))->toBe(['name' => null, 'value']);
});

test('parses props', function () {
    $directives = new Directives(
        app(Parser::class)->parse('@props([\'name\' => null, \'value\'])')
    );

    expect($directives->props())->toBe(['name', 'value']);
});

test('parses blaze directive', function () {
    $directives = new Directives(
        app(Parser::class)->parse('@blaze')
    );

    expect($directives->has('blaze'))->toBeTrue();
    expect($directives->get('blaze'))->toBe('');
});

test('parses blaze directive with params', function () {
    $directives = new Directives(
        app(Parser::class)->parse('@blaze(fold: true, safe: [\'name\'])')
    );

    expect($directives->blaze())->toBeTrue();
    expect($directives->blaze('fold'))->toBeTrue();
    expect($directives->blaze('safe'))->toBe(['name']);
    expect($directives->blaze('memo'))->toBeNull();
});
