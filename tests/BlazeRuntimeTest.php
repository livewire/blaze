<?php

use Livewire\Blaze\Runtime\BlazeRuntime;

test('currentComponentData returns current stack level only', function () {
    $runtime = new BlazeRuntime;

    $runtime->pushData(['foo' => 'bar']);
    $runtime->pushData(['baz' => 'qux']);

    expect($runtime->currentComponentData())->toBe(['baz' => 'qux']);
});

test('popData restores previous stack level', function () {
    $runtime = new BlazeRuntime;

    $runtime->pushData(['parent' => 'value']);
    $runtime->pushData(['child' => 'data']);

    expect($runtime->currentComponentData())->toBe(['child' => 'data']);

    $runtime->popData();

    expect($runtime->currentComponentData())->toBe(['parent' => 'value']);
});

test('slots are separate from data', function () {
    $runtime = new BlazeRuntime;

    $runtime->pushData(['foo' => 'bar']);
    $runtime->pushSlots(['header' => 'slot-header']);

    expect($runtime->currentComponentData())->toBe(['foo' => 'bar']);
    expect($runtime->mergedComponentSlots())->toBe(['header' => 'slot-header']);
});

test('mergedComponentSlots merges all levels', function () {
    $runtime = new BlazeRuntime;

    $runtime->pushData([]);
    $runtime->pushSlots(['header' => 'first']);
    $runtime->pushData([]);
    $runtime->pushSlots(['footer' => 'second']);

    expect($runtime->mergedComponentSlots())->toBe(['header' => 'first', 'footer' => 'second']);
});

test('child slots override parent slots with same name', function () {
    $runtime = new BlazeRuntime;

    $runtime->pushData([]);
    $runtime->pushSlots(['header' => 'parent-header']);
    $runtime->pushData([]);
    $runtime->pushSlots(['header' => 'child-header']);

    expect($runtime->mergedComponentSlots())->toBe(['header' => 'child-header']);
});

test('popData removes slots along with data', function () {
    $runtime = new BlazeRuntime;

    $runtime->pushData(['a' => 1]);
    $runtime->pushSlots(['slotA' => 'A']);
    $runtime->pushData(['b' => 2]);
    $runtime->pushSlots(['slotB' => 'B']);

    $runtime->popData();

    expect($runtime->mergedComponentSlots())->toBe(['slotA' => 'A']);
});

test('getConsumableData walks stack for @aware', function () {
    $runtime = new BlazeRuntime;

    $runtime->pushData(['color' => 'blue', 'size' => 'lg']);
    $runtime->pushData(['variant' => 'primary']);

    expect($runtime->getConsumableData('color'))->toBe('blue');
    expect($runtime->getConsumableData('variant'))->toBe('primary');
    expect($runtime->getConsumableData('missing', 'default'))->toBe('default');
});

test('getConsumableData checks slots before data', function () {
    $runtime = new BlazeRuntime;

    $runtime->pushData(['trigger' => 'data-trigger']);
    $runtime->pushSlots(['trigger' => 'slot-trigger']);

    expect($runtime->getConsumableData('trigger'))->toBe('slot-trigger');
});
