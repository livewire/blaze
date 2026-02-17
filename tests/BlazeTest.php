<?php

use Illuminate\Support\Facades\Blade;

test('ignores verbatim blocks', function () {
    $input = '@verbatim<x-input />@endverbatim';

    expect(Blade::render($input))->toBe('<x-input />');
});

test('ignores php blocks', function () {
    $input = "<?php echo '<x-input>'; ?>";

    expect(Blade::render($input))->toBe('<x-input>');
})->skip();

test('ignores php directives', function () {
    $input = "@php echo '<x-input />'; @endphp";

    expect(Blade::render($input))->toBe('<x-input />');
});

test('ignores comments', function () {
    $input = '{{-- <x-input /> --}}';

    expect(Blade::render($input))->toBe('');
});