<?php

use Livewire\Blaze\Runtime\BlazeRuntime;

beforeEach(function () {
    $this->runtime = new BlazeRuntime;
});

test('currentComponentData returns current stack level only', function () {
    $this->runtime->pushData(['foo' => 'bar']);
    $this->runtime->pushData(['baz' => 'qux']);

    expect($this->runtime->currentComponentData())->toBe(['baz' => 'qux']);
});

test('popData restores previous stack level', function () {
    $this->runtime->pushData(['parent' => 'value']);
    $this->runtime->pushData(['child' => 'data']);

    expect($this->runtime->currentComponentData())->toBe(['child' => 'data']);

    $this->runtime->popData();

    expect($this->runtime->currentComponentData())->toBe(['parent' => 'value']);
});

test('slots are separate from data', function () {
    $this->runtime->pushData(['foo' => 'bar']);
    $this->runtime->pushSlots(['header' => 'slot-header']);

    expect($this->runtime->currentComponentData())->toBe(['foo' => 'bar']);
    expect($this->runtime->mergedComponentSlots())->toBe(['header' => 'slot-header']);
});

test('mergedComponentSlots merges all levels', function () {
    $this->runtime->pushData([]);
    $this->runtime->pushSlots(['header' => 'first']);
    $this->runtime->pushData([]);
    $this->runtime->pushSlots(['footer' => 'second']);

    expect($this->runtime->mergedComponentSlots())->toBe(['header' => 'first', 'footer' => 'second']);
});

test('child slots override parent slots with same name', function () {
    $this->runtime->pushData([]);
    $this->runtime->pushSlots(['header' => 'parent-header']);
    $this->runtime->pushData([]);
    $this->runtime->pushSlots(['header' => 'child-header']);

    expect($this->runtime->mergedComponentSlots())->toBe(['header' => 'child-header']);
});

test('popData removes slots along with data', function () {
    $this->runtime->pushData(['a' => 1]);
    $this->runtime->pushSlots(['slotA' => 'A']);
    $this->runtime->pushData(['b' => 2]);
    $this->runtime->pushSlots(['slotB' => 'B']);

    $this->runtime->popData();

    expect($this->runtime->mergedComponentSlots())->toBe(['slotA' => 'A']);
});

test('getConsumableData walks stack for @aware', function () {
    $this->runtime->pushData(['color' => 'blue', 'size' => 'lg']);
    $this->runtime->pushData(['variant' => 'primary']);

    expect($this->runtime->getConsumableData('color'))->toBe('blue');
    expect($this->runtime->getConsumableData('variant'))->toBe('primary');
    expect($this->runtime->getConsumableData('missing', 'default'))->toBe('default');
});

test('getConsumableData checks slots before data', function () {
    $this->runtime->pushData(['trigger' => 'data-trigger']);
    $this->runtime->pushSlots(['trigger' => 'slot-trigger']);

    expect($this->runtime->getConsumableData('trigger'))->toBe('slot-trigger');
});
