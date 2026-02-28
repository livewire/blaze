<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Livewire\Blaze\Blaze;

test('renders components', function () {
    Artisan::call('view:clear');
    
    view('inputs')->render();
})->throwsNoExceptions();

test('renders components with blaze off', function () {
    Artisan::call('view:clear');

    Blaze::disable();
    
    view('inputs')->render();
})->throwsNoExceptions();

test('renders components with blaze off and debug mode on', function () {
    Artisan::call('view:clear');

    Blaze::disable();
    Blaze::debug();
    
    view('inputs')->render();
})->throwsNoExceptions();

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

test('supports php engine', function () {
    // Make sure our hooks do not break views
    // rendered using the regular php engine.
    view('php-view')->render();
})->throwsNoExceptions();