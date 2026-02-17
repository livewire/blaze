<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Artisan;

beforeEach(fn () => Artisan::call('view:clear'));

test('aware props are merged into the component', function () {
    $input = <<<'BLADE'
        <x-foldable.wrapper type="number">
            <x-foldable.input-aware />
        </x-foldable.wrapper>
        BLADE
    ;

    expect(Blade::render($input))->toEqualCollapsingWhitespace(
        '<div> <input type="number" > </div>'
    );
});

test('default aware values are merged into the component', function () {
    $input = <<<'BLADE'
        <x-foldable.input-aware />
        BLADE
    ;

    expect(Blade::render($input))->toEqualCollapsingWhitespace(
        '<input type="text" >'
    );
});