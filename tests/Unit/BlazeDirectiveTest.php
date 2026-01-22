<?php

use Livewire\Blaze\Directive\BlazeDirective;

describe('getParameters', function () {
    it('returns null when no @blaze directive exists', function () {
        $source = '@props(["name"])';

        expect(BlazeDirective::getParameters($source))->toBeNull();
    });

    it('returns empty array for @blaze without parameters', function () {
        $source = '@blaze';

        expect(BlazeDirective::getParameters($source))->toBe([]);
    });

    it('parses boolean parameters', function () {
        $source = '@blaze(fold: true)';

        expect(BlazeDirective::getParameters($source))->toBe(['fold' => true]);
    });

    it('parses false boolean parameters', function () {
        $source = '@blaze(fold: false)';

        expect(BlazeDirective::getParameters($source))->toBe(['fold' => false]);
    });

    it('parses multiple boolean parameters', function () {
        $source = '@blaze(fold: true, memo: false)';

        expect(BlazeDirective::getParameters($source))->toBe([
            'fold' => true,
            'memo' => false,
        ]);
    });

    it('parses array parameters with single value', function () {
        $source = "@blaze(fold: true, safe: ['name'])";

        $params = BlazeDirective::getParameters($source);

        expect($params['fold'])->toBeTrue();
        expect($params['safe'])->toBe(['name']);
    });

    it('parses array parameters with multiple values', function () {
        $source = "@blaze(fold: true, safe: ['name', 'id', 'type'])";

        $params = BlazeDirective::getParameters($source);

        expect($params['fold'])->toBeTrue();
        expect($params['safe'])->toBe(['name', 'id', 'type']);
    });

    it('parses array parameters with double quotes', function () {
        $source = '@blaze(fold: true, safe: ["name", "id"])';

        $params = BlazeDirective::getParameters($source);

        expect($params['fold'])->toBeTrue();
        expect($params['safe'])->toBe(['name', 'id']);
    });

    it('parses mixed array and boolean parameters', function () {
        $source = "@blaze(fold: true, safe: ['name'], memo: true)";

        $params = BlazeDirective::getParameters($source);

        expect($params['fold'])->toBeTrue();
        expect($params['safe'])->toBe(['name']);
        expect($params['memo'])->toBeTrue();
    });

    it('handles empty array parameter', function () {
        $source = '@blaze(fold: true, safe: [])';

        $params = BlazeDirective::getParameters($source);

        expect($params['fold'])->toBeTrue();
        expect($params['safe'])->toBe([]);
    });

    it('parses @blaze with only array parameter', function () {
        $source = "@blaze(safe: ['name', 'id'])";

        $params = BlazeDirective::getParameters($source);

        expect($params)->toBe([
            'safe' => ['name', 'id'],
        ]);
    });
});
