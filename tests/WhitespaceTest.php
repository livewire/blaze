<?php

use Illuminate\Support\Facades\Artisan;

beforeEach(fn () => Artisan::call('view:clear'));

test('slots and loose content', fn () => compare(<<<'BLADE'
    Before
    <x-card>
        Body
        <x-slot name="header">
            Header
        </x-slot>
        Body
    </x-card>
    After
    BLADE
));

test('unblaze', fn () => compare(<<<'BLADE'
    <x-foldable.input-unblaze />
    BLADE,
));

test('sibling foldable components', fn () => compare(<<<'BLADE'
    <x-foldable.wrapper>Hello world</x-foldable.wrapper>
    <x-foldable.wrapper>Hello world</x-foldable.wrapper>
    BLADE,
));

test('nested foldable components', fn () => compare(<<<'BLADE'
    <x-foldable.wrapper>
        <x-foldable.wrapper>Hello world</x-foldable.wrapper>
    </x-foldable.wrapper>
    BLADE,
));

test('nested foldable components with new line', fn () => compare(<<<'BLADE'
    <x-foldable.wrapper>
        <x-foldable.wrapper-nl>Hello world</x-foldable.wrapper-nl>
    </x-foldable.wrapper>
    BLADE,
));

test('nested foldable components with aware wrappers', fn () => compare(<<<'BLADE'
    <x-foldable.wrapper>
        <x-foldable.wrapper>
            <x-input-aware-no-blaze />
        </x-foldable.wrapper>
    </x-foldable.wrapper>
    BLADE,
));

test('foldable components with unblaze only', fn () => compare(<<<'BLADE'
    <div>
        <x-foldable.unblaze-only />
    </div>
    BLADE,
));

test('nested foldable components with unblaze only', fn () => compare(<<<'BLADE'
    <x-foldable.unblaze-only-wrapper />
    BLADE,
));