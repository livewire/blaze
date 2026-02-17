<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Component;
use Livewire\Blaze\Blaze;

beforeEach(fn () => Artisan::call('view:clear'));

test('self closing', function () {
    $input = '<x-parity.input type="text" :disabled="$disabled" />';
    $data = ['disabled' => false];

    $blaze = Blade::render($input, $data, true);
    
    Blaze::disable();
    Artisan::call('view:clear');
    Component::flushCache();

    $blade = Blade::render($input, $data, true);

    expect($blaze)->toBe($blade);
});

test('slots', function () {
    $input = <<<'BLADE'
        <x-parity.card>
            <x-slot name="header">
                Header
            </x-slot>
            Body
        </x-parity.card>
        BLADE
    ;
    $data = [];

    $blaze = Blade::render($input, $data, true);
    
    Blaze::disable();
    Artisan::call('view:clear');
    Component::flushCache();

    $blade = Blade::render($input, $data, true);

    expect($blaze)->toBe($blade);
});

test('whitespace', function () {
    $input = <<<'BLADE'
        Before
        <x-parity.card>
            Body
            <x-slot name="header">
                Header
            </x-slot>
            Body
        </x-parity.card>
        After
        BLADE
    ;

    $blaze = Blade::render($input);
    
    Blaze::disable();
    Artisan::call('view:clear');
    Component::flushCache();

    $blade = Blade::render($input);

    expect($blaze)->toBe($blade);
});