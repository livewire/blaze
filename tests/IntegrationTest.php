<?php

use Illuminate\Support\Facades\Blade;

test('ignores verbatim blocks', function () {
    $input = '@verbatim<x-input />@endverbatim';

    expect(Blade::render($input))->toBe('<x-input />');
});

test('ignores php directives', function () {
    $input = "@php echo '<x-input />'; @endphp";

    expect(Blade::render($input))->toBe('<x-input />');
});

test('ignores comments', function () {
    $input = '{{-- <x-input /> --}}';

    expect(Blade::render($input))->toBe('');
});

// TODO: Install PHPStan, which probably would have caught this.
test('supports php engine', function () {
    // Make sure our hooks do not break views
    // rendered using the regular php engine.
    view('php-view')->render();
})->throwsNoExceptions();