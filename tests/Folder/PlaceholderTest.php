<?php

use Livewire\Blaze\Folder\Placeholder;

test('parses placeholders', function (string $value, int $index, ?string $copy, int $escapes) {
    $placeholder = Placeholder::parse($value);

    expect($placeholder->index)->toBe($index);
    expect($placeholder->copy)->toBe($copy);
    expect($placeholder->escapes)->toBe($escapes);
    expect((string) $placeholder)->toBe($value);
})->with([
    ['BLAZE_PLACEHOLDER_0_', 0, null, 0],
    ['BLAZE_PLACEHOLDER_12_', 12, null, 0],
    ['BLAZE_PLACEHOLDER_0_raw&_', 0, 'raw', 0],
    ['BLAZE_PLACEHOLDER_12_raw&_', 12, 'raw', 0],
    ['BLAZE_PLACEHOLDER_0_raw&amp;_', 0, 'raw', 1],
    ['BLAZE_PLACEHOLDER_0_raw&amp;amp;_', 0, 'raw', 2],
    ['BLAZE_PLACEHOLDER_0_bag&_', 0, 'bag', 0],
    ['BLAZE_PLACEHOLDER_0_bag&amp;_', 0, 'bag', 1],
]);

test('records escapes of bound placeholders', function (Closure $escape, int $escapes) {
    $placeholder = Placeholder::parse($escape((string) new Placeholder(0, copy: 'raw')));

    expect($placeholder->escapes)->toBe($escapes);
})->with([
    'raw' => [fn ($value) => $value, 0],
    'escaped' => [fn ($value) => e($value), 1],
    'escaped twice' => [fn ($value) => e(e($value)), 2],
    'escaped without double encoding' => [fn ($value) => e($value, false), 1],
    'sanitized' => [fn ($value) => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($value), 1],
    'merged' => [fn ($value) => (new \Illuminate\View\ComponentAttributeBag)->merge(['class' => $value])->get('class'), 1],
]);

test('gets the attribute bag copy of a bound placeholder', function () {
    expect((string) (new Placeholder(3, copy: 'raw'))->inBag())->toBe('BLAZE_PLACEHOLDER_3_bag&_');
});

test('does not parse values that are not placeholders', function (mixed $value) {
    expect(Placeholder::parse($value))->toBeNull();
})->with([
    'BLAZE_PLACEHOLDER__raw&_',
    'BLAZE_PLACEHOLDER_x_raw&_',
    'BLAZE_PLACEHOLDER_0_raw_',
    'BLAZE_PLACEHOLDER_0_copy&_',
    'BLAZE_PLACEHOLDER_0_RAW&_',
    'BLAZE_PLACEHOLDER_0_raw&AMP;_',
    'BLAZE_PLACEHOLDER_0_raw&amp_',
    'BLAZE_PLACEHOLDER_0_raw\u0026_',
    'BLAZE_PLACEHOLDER_0_raw%26_',
    'prefix BLAZE_PLACEHOLDER_0_raw&_',
    'BLAZE_PLACEHOLDER_0_raw&_ suffix',
    null,
    1,
]);
