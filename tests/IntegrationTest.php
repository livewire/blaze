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

test('protects php blocks from precompilers that inject php tags', function () {
    // Simulate Livewire's SupportMorphAwareBladeCompilation precompiler which
    // wraps morph markers in PHP open/close tags. The injected close-tag
    // terminates PHP mode even inside // comments, causing @if to be
    // compiled as a bare directive.
    app('blade.compiler')->precompiler(function ($template) {
        $open = '<' . '?php';
        $close = '?' . '>';
        $prefix = $open . ' if(true): ' . $close . '<!--[if BLOCK]><![endif]-->' . $open . ' endif; ' . $close;

        return preg_replace(
            '/(?<!\w)@if(?!\w)/',
            $prefix . '@if',
            $template
        );
    });

    $compiled = compile('php-comment-with-directive.blade.php');

    // If Blaze fails to protect the @php block content from precompilers,
    // the close-tag inside the injected prefix ends the // comment's PHP
    // context, leaving bare @if in inline HTML where compileStatements()
    // turns it into invalid PHP (if with no condition).
    expect($compiled)->not->toContain('<' . '?php if: ?' . '>');
});

// TODO: Install PHPStan, which probably would have caught this.
test('supports php engine', function () {
    // Make sure our hooks do not break views
    // rendered using the regular php engine.
    view('php-view')->render();
})->throwsNoExceptions();