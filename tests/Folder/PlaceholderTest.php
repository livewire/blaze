<?php

use Livewire\Blaze\Folder\Placeholder;

test('parses placeholders', function (string $value, int $index, bool $bound, bool $escapedCopy, int $escapes, bool $doubleEncoded) {
    $placeholder = Placeholder::parse($value);

    expect($placeholder->index)->toBe($index);
    expect($placeholder->bound)->toBe($bound);
    expect($placeholder->escapedCopy)->toBe($escapedCopy);
    expect($placeholder->escapes)->toBe($escapes);
    expect($placeholder->doubleEncoded)->toBe($doubleEncoded);
    expect((string) $placeholder)->toBe($value);
})->with([
    ['BLAZE_PLACEHOLDER_0_', 0, false, false, 0, true],
    ['BLAZE_PLACEHOLDER_12_', 12, false, false, 0, true],
    ['BLAZE_PLACEHOLDER_0_E_', 0, true, true, 1, true],
    ['BLAZE_PLACEHOLDER_12_E_', 12, true, true, 1, true],
    ['BLAZE_PLACEHOLDER_0_&_&amp;_', 0, true, false, 0, true],
    ['BLAZE_PLACEHOLDER_0_&amp;_&amp;amp;_', 0, true, false, 1, true],
    ['BLAZE_PLACEHOLDER_0_&amp;_&amp;_', 0, true, false, 1, false],
    ['BLAZE_PLACEHOLDER_0_&amp;amp;amp;_&amp;amp;amp;amp;_', 0, true, false, 3, true],
    ['BLAZE_PLACEHOLDER_0_&amp;amp;amp;_&amp;amp;amp;_', 0, true, false, 3, false],
]);

test('records escapes of the raw copy of bound placeholders', function (Closure $escape, int $escapes, bool $doubleEncoded) {
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

test('the escaped copy of bound placeholders is not changed by escaping', function (Closure $escape) {
    $placeholder = (string) (new Placeholder(0, bound: true))->escaped();

    expect($escape($placeholder))->toBe($placeholder);
})->with([
    'escaped' => fn ($value) => e($value),
    'escaped without double encoding' => fn ($value) => e($value, false),
    'escaped twice' => fn ($value) => e(e($value)),
    'sanitized' => fn ($value) => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($value),
    'merged' => fn ($value) => (new \Illuminate\View\ComponentAttributeBag)->merge(['class' => $value])->get('class'),
]);

test('sanitizes values like an attribute bag, turning the raw copy of a placeholder into its escaped copy', function (mixed $value, mixed $expected) {
    expect(Placeholder::sanitize($value))->toBe($expected);
})->with([
    'raw copy' => ['BLAZE_PLACEHOLDER_0_&_&amp;_', 'BLAZE_PLACEHOLDER_0_E_'],
    'raw copy escaped by the component' => ['BLAZE_PLACEHOLDER_0_&amp;_&amp;amp;_', 'BLAZE_PLACEHOLDER_0_E_'],
    'escaped copy' => ['BLAZE_PLACEHOLDER_0_E_', 'BLAZE_PLACEHOLDER_0_E_'],
    'placeholder of an attribute that is not bound' => ['BLAZE_PLACEHOLDER_0_', 'BLAZE_PLACEHOLDER_0_'],
    'string' => ['a & b', 'a &amp; b'],
    'false' => [false, false],
    'null' => [null, null],
    'true' => [true, true],
]);

test('tells copies of the same bound attribute apart from other values', function (mixed $a, mixed $b, bool $same) {
    expect(Placeholder::same($a, $b))->toBe($same);
})->with([
    'raw and escaped copy' => ['BLAZE_PLACEHOLDER_0_&_&amp;_', 'BLAZE_PLACEHOLDER_0_E_', true],
    'escaped raw copy and escaped copy' => ['BLAZE_PLACEHOLDER_0_&amp;_&amp;amp;_', 'BLAZE_PLACEHOLDER_0_E_', true],
    'same copy' => ['BLAZE_PLACEHOLDER_0_E_', 'BLAZE_PLACEHOLDER_0_E_', true],
    'different attributes' => ['BLAZE_PLACEHOLDER_0_E_', 'BLAZE_PLACEHOLDER_1_E_', false],
    'placeholder and string' => ['BLAZE_PLACEHOLDER_0_E_', 'text-sm', false],
    'strings' => ['text-sm', 'text-sm', false],
    'placeholder and null' => ['BLAZE_PLACEHOLDER_0_E_', null, false],
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
    'BLAZE_PLACEHOLDER_0_E',
    'BLAZE_PLACEHOLDER_0_E_E_',
    'BLAZE_PLACEHOLDER_0_E_&_&amp;_',
    'BLAZE_PLACEHOLDER_0_\u0026_\u0026amp;_',
    'BLAZE_PLACEHOLDER_0_%26_%26amp%3B_',
    'prefix BLAZE_PLACEHOLDER_0_&_&amp;_',
    'BLAZE_PLACEHOLDER_0_&_&amp;_ suffix',
    null,
    1,
]);
