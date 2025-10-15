<?php

use Illuminate\Support\Facades\Artisan;

describe('memoization', function () {
    beforeEach(function () {
        // Configure Blade to find our test components and views
        app('blade.compiler')->anonymousComponentNamespace('', 'x');
        app('blade.compiler')->anonymousComponentPath(__DIR__ . '/fixtures/components');

        // call artisan view:clear:
        Artisan::call('view:clear');
    });

    it('can memoize an impure component', function () {
        $template = '<x-memoize />';

        $renderedA = app('blade.compiler')->render($template);
        $renderedB = app('blade.compiler')->render($template);

        expect($renderedA)->toContain('<div>');
        expect($renderedA)->toBe($renderedB);
    });

    it('can memoize based on static attributes', function () {
        $renderedA = app('blade.compiler')->render('<x-memoize foo="bar" />');
        $renderedB = app('blade.compiler')->render('<x-memoize foo="bar" />');

        expect($renderedA)->toContain('<div>');
        expect($renderedA)->toBe($renderedB);

        $renderedA = app('blade.compiler')->render('<x-memoize foo="bar" />');
        $renderedB = app('blade.compiler')->render('<x-memoize foo="baz" />');

        expect($renderedA)->toContain('<div>');
        expect($renderedA)->not->toBe($renderedB);
    });

    it('memoization only works on self-closing components', function () {
        //
    });
});