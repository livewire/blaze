<?php

use Livewire\Blaze\Runtime\BlazeAttributeBag;

test('merge appends class values', function () {
    $bag = new BlazeAttributeBag(['class' => 'font-bold', 'name' => 'test']);

    expect((string) $bag->merge(['class' => 'mt-4']))
        ->toBe('class="mt-4 font-bold" name="test"');
});

test('merge keeps instance value over default for non-appendable keys', function () {
    $bag = new BlazeAttributeBag(['class' => 'font-bold', 'name' => 'test']);

    expect((string) $bag->merge(['class' => 'mt-4', 'name' => 'foo']))
        ->toBe('class="mt-4 font-bold" name="test"');
});

test('merge adds new attributes from defaults', function () {
    $bag = new BlazeAttributeBag(['class' => 'font-bold', 'name' => 'test']);

    expect((string) $bag->merge(['class' => 'mt-4', 'id' => 'bar']))
        ->toBe('class="mt-4 font-bold" id="bar" name="test"');
});

test('merge deduplicates class values', function () {
    $bag = new BlazeAttributeBag(['class' => 'foo']);

    expect((string) $bag->merge(['class' => 'foo']))->toBe('class="foo"');
});

test('merge filters falsey class values', function ($classValue) {
    expect((string) (new BlazeAttributeBag(['class' => $classValue]))->merge(['class' => 'foo']))->toBe('class="foo"');
})->with([
    'null' => [null],
    'zero' => [0],
]);

test('merge escapes html entities in defaults', function () {
    $bag = new BlazeAttributeBag([]);

    expect((string) $bag->merge(['test-escaped' => '<tag attr="attr">']))
        ->toBe('test-escaped="&lt;tag attr=&quot;attr&quot;&gt;"');
});

test('merge handles various value types correctly', function () {
    $bag = new BlazeAttributeBag([
        'test-string' => 'ok',
        'test-null' => null,
        'test-false' => false,
        'test-true' => true,
        'test-0' => 0,
        'test-0-string' => '0',
        'test-empty-string' => '',
    ]);

    expect((string) $bag)
        ->toBe('test-string="ok" test-true="test-true" test-0="0" test-0-string="0" test-empty-string=""');
});

test('merge deduplicates style values', function () {
    $bag = new BlazeAttributeBag(['style' => 'color:red;']);

    expect((string) $bag->merge(['style' => 'color:red;']))->toBe('style="color:red;"');
});

test('class method merges conditional classes', function () {
    $bag = new BlazeAttributeBag(['class' => 'font-bold', 'name' => 'test']);

    expect((string) $bag->class(['mt-4', 'ml-2' => true, 'mr-2' => false]))
        ->toBe('class="mt-4 ml-2 font-bold" name="test"');
});

test('style method merges styles', function () {
    $bag = new BlazeAttributeBag(['class' => 'font-bold', 'name' => 'test', 'style' => 'margin-top: 10px']);

    expect((string) $bag->style(['margin-top: 4px', 'margin-left: 10px;']))
        ->toBe('style="margin-top: 4px; margin-left: 10px; margin-top: 10px;" class="font-bold" name="test"');
});

test('only returns specified attributes', function () {
    $bag = new BlazeAttributeBag(['class' => 'font-bold', 'name' => 'test', 'id' => 'my-id']);

    expect((string) $bag->only('class'))->toBe('class="font-bold"');
    expect((string) $bag->only(['class', 'name']))->toBe('class="font-bold" name="test"');
});

test('except excludes specified attributes', function () {
    $bag = new BlazeAttributeBag(['class' => 'font-bold', 'name' => 'test', 'id' => 'my-id']);

    expect((string) $bag->except('class'))->toBe('name="test" id="my-id"');
    expect((string) $bag->except(['class', 'name']))->toBe('id="my-id"');
});

test('whereStartsWith and whereDoesntStartWith filter by prefix', function () {
    $bag = new BlazeAttributeBag(['class' => 'font-bold', 'name' => 'test']);

    expect((string) $bag->whereStartsWith('class'))->toBe('class="font-bold"');
    expect((string) $bag->whereDoesntStartWith('class'))->toBe('name="test"');
});

test('has returns true for present attributes regardless of value', function ($key) {
    $bag = new BlazeAttributeBag(['name' => 'test', 'href' => '', 'src' => null]);

    expect($bag->has($key))->toBeTrue();
})->with(['name', 'href', 'src']);

test('has returns false and missing returns true for absent attributes', function () {
    $bag = new BlazeAttributeBag(['name' => 'test']);

    expect($bag->has('class'))->toBeFalse();
    expect($bag->missing('class'))->toBeTrue();
});

test('hasAny returns true when at least one attribute exists', function () {
    $bag = new BlazeAttributeBag(['name' => 'test']);

    expect($bag->hasAny(['class', 'name']))->toBeTrue();
});

test('get retrieves attribute values with defaults', function () {
    $bag = new BlazeAttributeBag(['class' => 'font-bold']);

    expect($bag->get('class'))->toBe('font-bold');
    expect($bag->get('missing', 'default'))->toBe('default');
});

test('boolean true renders as attribute name', function () {
    $bag = new BlazeAttributeBag(['required' => true, 'disabled' => true]);

    expect((string) $bag)->toBe('required="required" disabled="disabled"');
});

test('x-data and wire attributes render with empty value', function ($attr, $expected) {
    expect((string) new BlazeAttributeBag([$attr => true]))->toBe($expected);
})->with([
    'x-data'       => ['x-data', 'x-data=""'],
    'wire:loading'  => ['wire:loading', 'wire:loading=""'],
]);

test('prepends appends instance value after default', function () {
    $bag = new BlazeAttributeBag(['data-controller' => 'outside-controller']);

    $result = $bag->merge(['data-controller' => $bag->prepends('inside-controller')]);

    expect($result->get('data-controller'))->toBe('inside-controller outside-controller');
});

test('can be invoked as callable', function () {
    $bag = new BlazeAttributeBag(['class' => 'font-bold', 'name' => 'test']);

    expect((string) $bag(['class' => 'mt-4']))
        ->toBe('class="mt-4 font-bold" name="test"');
});

test('filter removes attributes by callback', function () {
    $bag = new BlazeAttributeBag(['class' => 'font-bold', 'name' => 'test', 'id' => 'my-id']);

    expect((string) $bag->filter(fn ($v, $k) => $k !== 'name'))
        ->toBe('class="font-bold" id="my-id"');
});

test('supports array access', function () {
    $bag = new BlazeAttributeBag(['class' => 'font-bold']);

    expect(isset($bag['class']))->toBeTrue();
    expect($bag['class'])->toBe('font-bold');

    $bag['id'] = 'test-id';
    expect($bag['id'])->toBe('test-id');

    unset($bag['id']);
    expect(isset($bag['id']))->toBeFalse();
});

test('dot notation keys are treated literally', function () {
    $bag = new BlazeAttributeBag(['data.config' => 'value1']);

    expect($bag->has('data.config'))->toBeTrue();
    expect($bag->get('data.config'))->toBe('value1');
    expect($bag->has('data'))->toBeFalse();
});
