<?php

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Facade;

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

test('compiles using original app context when global container is swapped', function () {
    $compiler = app('blade.compiler');
    $originalApp = app();

    $swapped = new Container;
    Container::setInstance($swapped);
    Facade::setFacadeApplication($swapped);

    try {
        $compiled = $compiler->compileString("@php \$name = 'Taylor'; @endphp\n<div>{{ \$name }}</div>");
    } finally {
        Container::setInstance($originalApp);
        Facade::setFacadeApplication($originalApp);
    }

    expect($compiled)->not->toContain('@__raw_block_');
});

// TODO: Install PHPStan, which probably would have caught this.
test('supports php engine', function () {
    // Make sure our hooks do not break views
    // rendered using the regular php engine.
    view('php-view')->render();
})->throwsNoExceptions();
