<?php

use Livewire\Blaze\Blaze;

test('folds components', function () {
    $input = '<x-input type="number">';
    $output = '<input type="number">';

    expect(Blaze::compile($input))->toBe($output);
});

test('folds components with slots', function () {
    $input = '<x-card><x-slot:header>Header</x-slot:header>Body</x-card>';
    $output = '<div>Header | Body</div>';

    expect(Blaze::compile($input))->toBe($output);
});

test('aborts fold when dynamic attribute is passed', function () {
    $input = '<x-button :type="$type" />';

    expect(Blaze::compile($input))->not->toContain('<input');
});