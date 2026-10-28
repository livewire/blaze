<?php

use Illuminate\Support\Facades\Artisan;

beforeEach(fn () => Artisan::call('view:clear'));

test('verbatim', fn () => compare('@verbatim <x-input /> @endverbatim'));
test('comment', fn () => compare('{{-- <x-input /> --}}'));
test('php block', fn () => compare('@php // <x-input /> @endphp'));

test('basic', fn () => compare(<<<'BLADE'
    <x-input type="text" :disabled="$disabled" />

    <x-card>
        <x-slot name="header">
            Header
        </x-slot>
        Body
    </x-card>
    BLADE,
    ['disabled' => false],
));

test('aware', fn () => compare(<<<'BLADE'
    <x-wrapper type="number">
        <x-input-aware />
    </x-wrapper>
    BLADE
));

test('aware default', fn () => compare(<<<'BLADE'
    <x-input-aware />
    BLADE
));

test('nested aware', fn () => compare(<<<'BLADE'
    <x-nested-input-aware type="number" />
    BLADE
));

test('foldable aware', fn () => compare(<<<'BLADE'
    <x-foldable.wrapper type="number">
        <x-foldable.input-aware />
    </x-foldable.wrapper>
    BLADE
));

test('nested foldable aware', fn () => compare(<<<'BLADE'
    <x-foldable.nested-input-aware type="number" />
    BLADE
));

test('foldable child with unsafe aware prop', fn () => compare(<<<'BLADE'
    <x-foldable.wrapper :type="'number'">
        <x-foldable.input-aware-unsafe />
    </x-foldable.wrapper>
    BLADE,
));

test('foldable aware default', fn () => compare(<<<'BLADE'
    <x-foldable.input-aware />
    BLADE
));

test('foldable boolean attributes', fn () => compare(<<<'BLADE'
    <x-foldable.input :readonly="$readonly" />
    BLADE,
    ['readonly' => false],
));

test('foldable literal string attributes matching PHP constants', fn () => compare(<<<'BLADE'
    <x-foldable.input value="true" />
    <x-foldable.input value="false" />
    <x-foldable.input value="null" />
    BLADE
));

test('same component in a slot doesnt affect parents attributes', fn () => compare(<<<'BLADE'
    <x-card>
        <x-card x-data>
            Hello World
        </x-card>
    </x-card>
    BLADE
));

test('attributes and props', fn () => compare(<<<'BLADE'
    <x-attributes
        attr="foo"
        attr-kebab="bar"
        :str="str('hello')"
        :str-kebab="str('world')"
    >
        <x-slot:content class="p-2" data-foo="bar"></x-slot:content>
    </x-attributes>

    <x-props
        prop="foo"
        prop-kebab="bar"
        :str="str('hello')"
        :str-kebab="str('world')"
    >
        <x-slot:content class="p-2" data-foo="bar"></x-slot:content>
    </x-props>
    BLADE
));

test('merged styles are separated with a semicolon', fn () => compare(<<<'BLADE'
    <x-merged-style style="display: none" />
    BLADE
));

test('forwarded attributes are merged with attributes on the component', fn () => compare(<<<'BLADE'
    <x-forwarded-attributes class="outer" style="display: none" data-foo="outer" />
    BLADE
));

test('nested same component with different component in between', fn () => compare(<<<'BLADE'
    <x-card class="outer">
        <x-wrapper>
            Wrapped
        </x-wrapper>
        <x-card class="inner">
            <x-wrapper>
                Inner Wrapped
            </x-wrapper>
        </x-card>
    </x-card>
    BLADE
));

test('deeply nested same component with different components interleaved', fn () => compare(<<<'BLADE'
    <x-card class="outer">
        <x-wrapper>
            <x-card class="middle">
                <x-wrapper>
                    <x-card class="inner">
                        Inner
                    </x-card>
                </x-wrapper>
            </x-card>
        </x-wrapper>
    </x-card>
    BLADE
));

test('conditional slots', fn () => compare(<<<'BLADE'
    <x-card>
        @if(false)
        <x-slot:header>
            Header
        </x-slot:header>
        @endif
    </x-card>
    BLADE
));

test('foldable conditional slots', fn () => compare(<<<'BLADE'
    <x-foldable.card>
        @if(false)
        <x-slot:header>
            Header
        </x-slot:header>
        @endif
    </x-foldable.card>
    BLADE
));

test('foldable aware without props', fn () => compare(<<<'BLADE'
    <x-foldable.input-aware-no-props type="number" />
    BLADE
));

test('foldable kebab-case attributes as variables', fn () => compare(<<<'BLADE'
    <x-foldable.variables foo-bar="expected" />
    BLADE
));

test('duplicate attribute', fn () => compare(<<<'BLADE'
    <x-input type="email" type="number" />
    BLADE
));

test('folded component escapes its attribute bag passed to unblaze', fn () => compare(<<<'BLADE'
    <x-foldable.unblaze-bag :title="$value" name="Name" />
    BLADE, ['value' => '"><img src=x onerror=alert(1)> a"b<c>&d\'e']
));