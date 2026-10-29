<?php

use Livewire\Blaze\Folder\Placeholder;

test('parses placeholders', function (string $value, int $index, bool $bound, int $escapes, bool $doubleEncoded) {
    $placeholder = Placeholder::parse($value);

    expect($placeholder->index)->toBe($index);
    expect($placeholder->bound)->toBe($bound);
    expect($placeholder->escapes)->toBe($escapes);
    expect($placeholder->doubleEncoded)->toBe($doubleEncoded);
    expect((string) $placeholder)->toBe($value);
})->with([
    ['BLAZE_PLACEHOLDER_0_', 0, false, 0, true],
    ['BLAZE_PLACEHOLDER_12_', 12, false, 0, true],
    ['BLAZE_PLACEHOLDER_0_&_&amp;_', 0, true, 0, true],
    ['BLAZE_PLACEHOLDER_0_&amp;_&amp;amp;_', 0, true, 1, true],
    ['BLAZE_PLACEHOLDER_0_&amp;_&amp;_', 0, true, 1, false],
    ['BLAZE_PLACEHOLDER_0_&amp;amp;amp;_&amp;amp;amp;amp;_', 0, true, 3, true],
    ['BLAZE_PLACEHOLDER_0_&amp;amp;amp;_&amp;amp;amp;_', 0, true, 3, false],
]);

test('records escapes of bound placeholders', function (Closure $escape, int $escapes, bool $doubleEncoded) {
    $placeholder = Placeholder::parse($escape((string) new Placeholder(0, bound: true)));

    expect($placeholder->escapes)->toBe($escapes);
    expect($placeholder->doubleEncoded)->toBe($doubleEncoded);
})->with([
    'raw' => [fn ($value) => $value, 0, true],
    'escaped' => [fn ($value) => e($value), 1, true],
    'escaped without double encoding' => [fn ($value) => e($value, false), 1, false],
    'escaped twice' => [fn ($value) => e(e($value)), 2, true],
    'escaped twice, first without double encoding' => [fn ($value) => e(e($value, false)), 2, false],
    'escaped twice, second without double encoding' => [fn ($value) => e(e($value), false), 1, true],
]);

test('does not parse values that are not placeholders', function (mixed $value) {
    expect(Placeholder::parse($value))->toBeNull();
})->with([
    'BLAZE_PLACEHOLDER__&_&amp;_',
    'BLAZE_PLACEHOLDER_x_&_&amp;_',
    'BLAZE_PLACEHOLDER_0_&_',
    'BLAZE_PLACEHOLDER_0_&AMP;_&amp;_',
    'BLAZE_PLACEHOLDER_0_&_&amp;amp;_',
    'BLAZE_PLACEHOLDER_0_&amp;amp;_&amp;_',
    'prefix BLAZE_PLACEHOLDER_0_&_&amp;_',
    'BLAZE_PLACEHOLDER_0_&_&amp;_ suffix',
    null,
    1,
]);
