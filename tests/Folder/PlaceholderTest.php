<?php

use Livewire\Blaze\Folder\Placeholder;

test('parses placeholders', function (string $value, int $index, ?string $copy, int $escapes, int $encodings) {
    $placeholder = Placeholder::parse($value);

    expect($placeholder->index)->toBe($index);
    expect($placeholder->copy)->toBe($copy);
    expect($placeholder->escapes)->toBe($escapes);
    expect($placeholder->encodings)->toBe($encodings);
    expect((string) $placeholder)->toBe($value);
})->with([
    ['BLAZE_PLACEHOLDER_0_', 0, null, 0, 1],
    ['BLAZE_PLACEHOLDER_12_', 12, null, 0, 1],
    ['BLAZE_PLACEHOLDER_0_raw&_&amp;_', 0, 'raw', 0, 1],
    ['BLAZE_PLACEHOLDER_12_raw&_&amp;_', 12, 'raw', 0, 1],
    ['BLAZE_PLACEHOLDER_0_raw&amp;_&amp;amp;_', 0, 'raw', 1, 2],
    ['BLAZE_PLACEHOLDER_0_raw&amp;amp;_&amp;amp;amp;_', 0, 'raw', 2, 3],
    ['BLAZE_PLACEHOLDER_0_raw&amp;_&amp;_', 0, 'raw', 1, 1],
    ['BLAZE_PLACEHOLDER_0_raw&_&_', 0, 'raw', 0, 0],
    ['BLAZE_PLACEHOLDER_0_bag&_&amp;_', 0, 'bag', 0, 1],
    ['BLAZE_PLACEHOLDER_0_bag&amp;_&amp;amp;_', 0, 'bag', 1, 2],
]);

test('records escapes of bound placeholders', function (Closure $escape, int $escapes, bool $doubleEncode) {
    $placeholder = Placeholder::parse($escape((string) new Placeholder(0, copy: 'raw')));

    expect($placeholder->is('raw', $escapes, $doubleEncode))->toBeTrue();
})->with([
    'raw' => [fn ($value) => $value, 0, true],
    'escaped' => [fn ($value) => e($value), 1, true],
    'escaped twice' => [fn ($value) => e(e($value)), 2, true],
    'escaped without double encoding' => [fn ($value) => e($value, false), 1, false],
    'escaped, then without double encoding' => [fn ($value) => e(e($value), false), 1, true],
    'decoded and escaped' => [fn ($value) => e(htmlspecialchars_decode($value, ENT_QUOTES)), 1, false],
    'escaped, decoded and escaped' => [fn ($value) => e(htmlspecialchars_decode(e($value), ENT_QUOTES)), 1, true],
    'sanitized' => [fn ($value) => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($value), 1, true],
    'merged' => [fn ($value) => (new \Illuminate\View\ComponentAttributeBag)->merge(['class' => $value])->get('class'), 1, true],
]);

test('records decoding of bound placeholders', function () {
    $placeholder = Placeholder::parse(htmlspecialchars_decode((string) new Placeholder(0, copy: 'raw'), ENT_QUOTES));

    expect($placeholder->escapes)->toBe(0);
    expect($placeholder->encodings)->toBe(0);
    expect($placeholder->is('raw'))->toBeFalse();
});

test('gets the attribute bag copy of a bound placeholder', function () {
    expect((string) (new Placeholder(3, copy: 'raw'))->inBag())->toBe('BLAZE_PLACEHOLDER_3_bag&_&amp;_');
});

test('does not parse values that are not placeholders', function (mixed $value) {
    expect(Placeholder::parse($value))->toBeNull();
})->with([
    'BLAZE_PLACEHOLDER__raw&_&amp;_',
    'BLAZE_PLACEHOLDER_x_raw&_&amp;_',
    'BLAZE_PLACEHOLDER_0_raw_',
    'BLAZE_PLACEHOLDER_0_raw&_',
    'BLAZE_PLACEHOLDER_0_copy&_&amp;_',
    'BLAZE_PLACEHOLDER_0_RAW&_&amp;_',
    'BLAZE_PLACEHOLDER_0_raw&_&AMP;_',
    'BLAZE_PLACEHOLDER_0_raw&_&amp_',
    'BLAZE_PLACEHOLDER_0_raw\u0026_\u0026amp;_',
    'BLAZE_PLACEHOLDER_0_raw%26_%26amp;_',
    'prefix BLAZE_PLACEHOLDER_0_raw&_&amp;_',
    'BLAZE_PLACEHOLDER_0_raw&_&amp;_ suffix',
    null,
    1,
]);
