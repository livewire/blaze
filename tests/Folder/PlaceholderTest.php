<?php

use Livewire\Blaze\Folder\Placeholder;

test('parses placeholders', function (string $value, int $index, bool $bound, int $escapes) {
    $placeholder = Placeholder::parse($value);

    expect($placeholder->index)->toBe($index);
    expect($placeholder->bound)->toBe($bound);
    expect($placeholder->escapes)->toBe($escapes);
    expect((string) $placeholder)->toBe($value);
})->with([
    ['BLAZE_PLACEHOLDER_0_', 0, false, 0],
    ['BLAZE_PLACEHOLDER_12_', 12, false, 0],
    ['BLAZE_PLACEHOLDER_0_&_', 0, true, 0],
    ['BLAZE_PLACEHOLDER_0_&amp;_', 0, true, 1],
    ['BLAZE_PLACEHOLDER_0_&amp;amp;amp;_', 0, true, 3],
]);

test('does not parse values that are not placeholders', function (mixed $value) {
    expect(Placeholder::parse($value))->toBeNull();
})->with([
    'BLAZE_PLACEHOLDER__&_',
    'BLAZE_PLACEHOLDER_x_&_',
    'BLAZE_PLACEHOLDER_0_&AMP;_',
    'prefix BLAZE_PLACEHOLDER_0_&_',
    'BLAZE_PLACEHOLDER_0_&_ suffix',
    null,
    1,
]);
