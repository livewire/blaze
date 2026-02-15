<?php

use Illuminate\Support\Facades\Blade;
use Livewire\Blaze\Exceptions\InvalidBlazeFoldUsageException;

test('folds static parts and preserves @unblaze content as dynamic', function () {
    $compiled = app('blaze')->compile('<x-with-unblaze />');

    expect($compiled)->toContain('<h1>Static Header</h1>');
    expect($compiled)->toContain('Static Footer');
    expect($compiled)->toContain('{{ $dynamicValue }}');
    expect($compiled)->not->toContain('<x-with-unblaze');
});

test('handles @unblaze with scope parameter', function () {
    $rendered = Blade::render(
        '<?php $message = "Hello World"; ?> <x-with-unblaze-scope :message="$message" />'
    );

    expect($rendered)->toContain('<div class="dynamic">Hello World</div>');
    expect($rendered)->toContain('<h2>Title</h2>');
    expect($rendered)->toContain('<p>Static paragraph</p>');
});

test('allows $errors inside @unblaze blocks', function () {
    $compiled = app('blaze')->compile('<x-with-errors-inside-unblaze />');

    expect($compiled)->toContain('form-input');
    expect($compiled)->toContain('$errors');
});

test('throws for $errors outside @unblaze blocks', function () {
    expect(fn() => app('blaze')->compile('<x-with-errors-outside-unblaze />'))
        ->toThrow(InvalidBlazeFoldUsageException::class);
});
