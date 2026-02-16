<?php

use Livewire\Blaze\Directive\BlazeDirective;

test('returns null when no @blaze directive exists', function () {
    expect(BlazeDirective::getParameters('@props(["name"])'))->toBeNull();
});

test('returns empty array for @blaze without parameters', function () {
    expect(BlazeDirective::getParameters('@blaze'))->toBe([]);
});

test('parses boolean parameters', function ($input, $expected) {
    expect(BlazeDirective::getParameters($input))->toBe($expected);
})->with([
    'true'          => ['@blaze(fold: true)', ['fold' => true]],
    'false'         => ['@blaze(fold: false)', ['fold' => false]],
    'multiple'      => ['@blaze(fold: true, memo: false)', ['fold' => true, 'memo' => false]],
]);

test('parses array parameters', function ($input, $expectedSafe) {
    $params = BlazeDirective::getParameters($input);

    expect($params['safe'])->toBe($expectedSafe);
})->with([
    'single value'    => ["@blaze(fold: true, safe: ['name'])", ['name']],
    'multiple values' => ["@blaze(fold: true, safe: ['name', 'id', 'type'])", ['name', 'id', 'type']],
    'double quotes'   => ['@blaze(fold: true, safe: ["name", "id"])', ['name', 'id']],
    'empty array'     => ['@blaze(fold: true, safe: [])', []],
    'only arrays'     => ["@blaze(safe: ['name', 'id'])", ['name', 'id']],
]);

test('parses mixed array and boolean parameters', function () {
    $params = BlazeDirective::getParameters("@blaze(fold: true, safe: ['name'], memo: true)");

    expect($params)->toBe(['safe' => ['name'], 'fold' => true, 'memo' => true]);
});
