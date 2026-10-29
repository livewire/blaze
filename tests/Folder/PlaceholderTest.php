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
    ['BLAZE_PLACEHOLDER_12_&_', 12, true, 0],
    ['BLAZE_PLACEHOLDER_0_&amp;_', 0, true, 1],
    ['BLAZE_PLACEHOLDER_0_&amp;amp;amp;_', 0, true, 3],
]);

test('records escapes of bound placeholders', function (Closure $escape, int $escapes) {
    $placeholder = Placeholder::parse($escape((string) new Placeholder(0, bound: true)));

    expect($placeholder->escapes)->toBe($escapes);
})->with([
    'raw' => [fn ($value) => $value, 0],
    'escaped' => [fn ($value) => e($value), 1],
    'escaped twice' => [fn ($value) => e(e($value)), 2],
    'sanitized' => [fn ($value) => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($value), 1],
    'merged' => [fn ($value) => (new \Illuminate\View\ComponentAttributeBag)->merge(['class' => $value])->get('class'), 1],
]);

test('tells copies of the same bound attribute apart from other values', function (mixed $a, mixed $b, bool $same) {
    expect(Placeholder::same($a, $b))->toBe($same);
})->with([
    'raw and escaped copy' => ['BLAZE_PLACEHOLDER_0_&_', 'BLAZE_PLACEHOLDER_0_&amp;_', true],
    'same copy' => ['BLAZE_PLACEHOLDER_0_&amp;_', 'BLAZE_PLACEHOLDER_0_&amp;_', true],
    'different attributes' => ['BLAZE_PLACEHOLDER_0_&amp;_', 'BLAZE_PLACEHOLDER_1_&amp;_', false],
    'placeholder and string' => ['BLAZE_PLACEHOLDER_0_&amp;_', 'text-sm', false],
    'strings' => ['text-sm', 'text-sm', false],
    'placeholder and null' => ['BLAZE_PLACEHOLDER_0_&amp;_', null, false],
]);

test('does not parse values that are not placeholders', function (mixed $value) {
    expect(Placeholder::parse($value))->toBeNull();
})->with([
    'BLAZE_PLACEHOLDER__&_',
    'BLAZE_PLACEHOLDER_x_&_',
    'BLAZE_PLACEHOLDER_0_&',
    'BLAZE_PLACEHOLDER_0_&AMP;_',
    'BLAZE_PLACEHOLDER_0_&amp_',
    'BLAZE_PLACEHOLDER_0_\u0026_',
    'BLAZE_PLACEHOLDER_0_%26_',
    'prefix BLAZE_PLACEHOLDER_0_&_',
    'BLAZE_PLACEHOLDER_0_&_ suffix',
    null,
    1,
]);
