<?php

use Livewire\Blaze\Runtime\BlazeRuntime;

describe('BlazeRuntime currentComponentData', function () {
    it('returns current stack level data only', function () {
        $runtime = new BlazeRuntime;

        $runtime->pushData(['foo' => 'bar']);
        $runtime->pushData(['baz' => 'qux']);

        // currentComponentData returns only the current level's data
        expect($runtime->currentComponentData())->toBe(['baz' => 'qux']);
    });

    it('returns only current level after push', function () {
        $runtime = new BlazeRuntime;

        $runtime->pushData(['color' => 'blue', 'size' => 'lg']);
        $runtime->pushData(['color' => 'red']);

        // Only current level's data
        expect($runtime->currentComponentData())->toBe(['color' => 'red']);
    });

    it('returns previous level data after pop', function () {
        $runtime = new BlazeRuntime;

        $runtime->pushData(['parent' => 'value']);
        $runtime->pushData(['child' => 'data']);

        expect($runtime->currentComponentData())->toBe(['child' => 'data']);

        $runtime->popData();

        expect($runtime->currentComponentData())->toBe(['parent' => 'value']);
    });

    it('does not include slots in data (slots are passed separately)', function () {
        $runtime = new BlazeRuntime;

        $runtime->pushData(['foo' => 'bar']);
        $runtime->pushSlots(['header' => 'slot-header']);

        // currentComponentData only returns data, not slots
        expect($runtime->currentComponentData())->toBe(['foo' => 'bar']);
        // Slots are available via mergedComponentSlots
        expect($runtime->mergedComponentSlots())->toBe(['header' => 'slot-header']);
    });
});

describe('BlazeRuntime slots stack operations', function () {
    it('mergedComponentSlots returns merged slots', function () {
        $runtime = new BlazeRuntime;

        $runtime->pushData([]);
        $runtime->pushSlots(['header' => 'first']);
        $runtime->pushData([]);
        $runtime->pushSlots(['footer' => 'second']);

        expect($runtime->mergedComponentSlots())->toBe(['header' => 'first', 'footer' => 'second']);
    });

    it('child slots override parent slots with same name', function () {
        $runtime = new BlazeRuntime;

        $runtime->pushData([]);
        $runtime->pushSlots(['header' => 'parent-header']);
        $runtime->pushData([]);
        $runtime->pushSlots(['header' => 'child-header']);

        expect($runtime->mergedComponentSlots())->toBe(['header' => 'child-header']);
    });

    it('popData removes slots along with data', function () {
        $runtime = new BlazeRuntime;

        $runtime->pushData(['a' => 1]);
        $runtime->pushSlots(['slotA' => 'A']);
        $runtime->pushData(['b' => 2]);
        $runtime->pushSlots(['slotB' => 'B']);

        expect($runtime->mergedComponentSlots())->toBe(['slotA' => 'A', 'slotB' => 'B']);

        $runtime->popData();

        expect($runtime->mergedComponentSlots())->toBe(['slotA' => 'A']);
    });
});

describe('BlazeRuntime data stack operations', function () {
    it('pushes and pops data correctly', function () {
        $runtime = new BlazeRuntime;

        $runtime->pushData(['a' => 1]);
        $runtime->pushData(['b' => 2]);
        $runtime->pushData(['c' => 3]);

        expect($runtime->currentComponentData())->toBe(['c' => 3]);

        $runtime->popData();
        expect($runtime->currentComponentData())->toBe(['b' => 2]);

        $runtime->popData();
        expect($runtime->currentComponentData())->toBe(['a' => 1]);
    });

    it('getConsumableData walks stack for @aware', function () {
        $runtime = new BlazeRuntime;

        $runtime->pushData(['color' => 'blue', 'size' => 'lg']);
        $runtime->pushData(['variant' => 'primary']);

        // Should find color from parent
        expect($runtime->getConsumableData('color'))->toBe('blue');
        // Should find variant from current
        expect($runtime->getConsumableData('variant'))->toBe('primary');
        // Should return default for missing key
        expect($runtime->getConsumableData('missing', 'default'))->toBe('default');
    });

    it('getConsumableData checks slots before data', function () {
        $runtime = new BlazeRuntime;

        $runtime->pushData(['trigger' => 'data-trigger']);
        $runtime->pushSlots(['trigger' => 'slot-trigger']);

        // Slot should take precedence
        expect($runtime->getConsumableData('trigger'))->toBe('slot-trigger');
    });
});
