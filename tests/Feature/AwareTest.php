<?php

use Livewire\Blaze\Compiler\AwareCompiler;
use Livewire\Blaze\Exceptions\InvalidAwareDefinitionException;

beforeEach(function () {
    app('blade.compiler')->anonymousComponentPath(__DIR__ . '/fixtures');
});

describe('basic @aware functionality', function () {
    it('gets prop from parent component', function () {
        $result = blade(
            components: [
                'menu' => <<<'BLADE'
                    @blaze
                    @props(['color' => 'gray'])
                    <ul class="bg-{{ $color }}-100">{{ $slot }}</ul>
                    BLADE
                ,
                'menu-item' => <<<'BLADE'
                    @blaze
                    @aware(['color' => 'gray'])
                    <li class="text-{{ $color }}-800">{{ $slot }}</li>
                    BLADE
                ,
            ],
            view: '<x-menu color="blue"><x-menu-item>Item</x-menu-item></x-menu>',
        );

        expect($result)->toContain('bg-blue-100');
        expect($result)->toContain('text-blue-800');
    });

    it('uses default when parent does not have the prop', function () {
        $result = blade(
            components: [
                'menu-item' => <<<'BLADE'
                    @blaze
                    @aware(['color' => 'gray'])
                    <li class="text-{{ $color }}-800">{{ $slot }}</li>
                    BLADE
                ,
            ],
            view: '<x-menu-item>Item</x-menu-item>',
        );

        expect($result)->toContain('text-gray-800');
    });

    it('uses default when prop is not passed to parent', function () {
        $result = blade(
            components: [
                'menu' => <<<'BLADE'
                    @blaze
                    @props(['color' => 'gray'])
                    <ul class="bg-{{ $color }}-100">{{ $slot }}</ul>
                    BLADE
                ,
                'menu-item' => <<<'BLADE'
                    @blaze
                    @aware(['color' => 'gray'])
                    <li class="text-{{ $color }}-800">{{ $slot }}</li>
                    BLADE
                ,
            ],
            view: '<x-menu><x-menu-item>Item</x-menu-item></x-menu>',
        );

        expect($result)->toContain('bg-gray-100');
        expect($result)->toContain('text-gray-800');
    });
});

describe('nested components', function () {
    it('child gets prop from immediate parent', function () {
        $result = blade(
            components: [
                'outer' => <<<'BLADE'
                    @blaze
                    @props(['variant' => 'primary'])
                    <div class="outer-{{ $variant }}">{{ $slot }}</div>
                    BLADE
                ,
                'inner' => <<<'BLADE'
                    @blaze
                    @aware(['variant' => 'default', 'spacing' => 'default'])
                    <span class="inner-{{ $variant }}-{{ $spacing }}">{{ $slot }}</span>
                    BLADE
                ,
            ],
            view: '<x-outer variant="success"><x-inner>Text</x-inner></x-outer>',
        );

        expect($result)->toContain('outer-success');
        expect($result)->toContain('inner-success-default');
    });

    it('grandchild gets prop from grandparent', function () {
        $result = blade(
            components: [
                'outer' => <<<'BLADE'
                    @blaze
                    @props(['variant' => 'primary'])
                    <div class="outer-{{ $variant }}">{{ $slot }}</div>
                    BLADE
                ,
                'middle' => <<<'BLADE'
                    @blaze
                    @props(['spacing' => 'normal'])
                    <div class="middle-{{ $spacing }}">{{ $slot }}</div>
                    BLADE
                ,
                'inner' => <<<'BLADE'
                    @blaze
                    @aware(['variant' => 'default', 'spacing' => 'default'])
                    <span class="inner-{{ $variant }}-{{ $spacing }}">{{ $slot }}</span>
                    BLADE
                ,
            ],
            view: <<<'BLADE'
                <x-outer variant="success">
                    <x-middle spacing="tight">
                        <x-inner>Text</x-inner>
                    </x-middle>
                </x-outer>
                BLADE
            ,
        );

        expect($result)->toContain('outer-success');
        expect($result)->toContain('middle-tight');
        expect($result)->toContain('inner-success-tight');
    });

    it('nearest ancestor wins when same prop exists at multiple levels', function () {
        $result = blade(
            components: [
                'outer' => <<<'BLADE'
                    @blaze
                    @props(['color' => 'outer'])
                    <div class="level-outer-{{ $color }}">{{ $slot }}</div>
                    BLADE
                ,
                'middle' => <<<'BLADE'
                    @blaze
                    @props(['color' => 'middle'])
                    <div class="level-middle-{{ $color }}">{{ $slot }}</div>
                    BLADE
                ,
                'inner' => <<<'BLADE'
                    @blaze
                    @aware(['color' => 'inner'])
                    <span class="level-inner-{{ $color }}">{{ $slot }}</span>
                    BLADE
                ,
            ],
            view: '<x-outer color="red"><x-middle color="blue"><x-inner>Text</x-inner></x-middle></x-outer>',
        );

        expect($result)->toContain('level-outer-red');
        expect($result)->toContain('level-middle-blue');
        expect($result)->toContain('level-inner-blue');
    });
});

describe('@aware without default', function () {
    it('returns null when parent does not have prop', function () {
        $result = blade(
            components: [
                'child' => <<<'BLADE'
                    @blaze
                    @aware(['color'])
                    <span class="text-{{ $color ?? 'undefined' }}">{{ $slot }}</span>
                    BLADE
                ,
            ],
            view: '<x-child>Test</x-child>',
        );

        expect($result)->toContain('text-undefined');
    });

    it('gets value when parent has prop', function () {
        $result = blade(
            components: [
                'menu' => <<<'BLADE'
                    @blaze
                    @props(['color' => 'gray'])
                    <ul class="bg-{{ $color }}-100">{{ $slot }}</ul>
                    BLADE
                ,
                'child' => <<<'BLADE'
                    @blaze
                    @aware(['color'])
                    <span class="text-{{ $color ?? 'undefined' }}">{{ $slot }}</span>
                    BLADE
                ,
            ],
            view: '<x-menu color="purple"><x-child>Test</x-child></x-menu>',
        );

        expect($result)->toContain('text-purple');
    });
});

describe('multiple @aware variables', function () {
    it('gets multiple props from parent', function () {
        $result = blade(
            components: [
                'menu' => <<<'BLADE'
                    @blaze
                    @props(['color' => 'gray', 'size' => 'md'])
                    <ul class="bg-{{ $color }}-100 text-{{ $size }}">{{ $slot }}</ul>
                    BLADE
                ,
                'child' => <<<'BLADE'
                    @blaze
                    @aware(['color' => 'gray', 'size' => 'md', 'disabled'])
                    <button class="text-{{ $color }} size-{{ $size }}" {{ $disabled ? 'disabled' : '' }}>{{ $slot }}</button>
                    BLADE
                ,
            ],
            view: '<x-menu color="red" size="lg"><x-child>Click</x-child></x-menu>',
        );

        expect($result)->toContain('text-red');
        expect($result)->toContain('size-lg');
    });

    it('uses defaults for missing props', function () {
        $result = blade(
            components: [
                'menu' => <<<'BLADE'
                    @blaze
                    @props(['color' => 'gray', 'size' => 'md'])
                    <ul class="bg-{{ $color }}-100 text-{{ $size }}">{{ $slot }}</ul>
                    BLADE
                ,
                'child' => <<<'BLADE'
                    @blaze
                    @aware(['color' => 'gray', 'size' => 'md', 'disabled'])
                    <button class="text-{{ $color }} size-{{ $size }}" {{ $disabled ? 'disabled' : '' }}>{{ $slot }}</button>
                    BLADE
                ,
            ],
            view: '<x-menu color="green"><x-child>Click</x-child></x-menu>',
        );

        expect($result)->toContain('text-green');
        expect($result)->toContain('size-md');
    });
});

describe('edge cases', function () {
    it('handles empty @aware array', function () {
        $compiler = new AwareCompiler;

        $result = $compiler->compile('[]');

        expect($result)->toBe('');
    });

    it('handles component with both @props and @aware', function () {
        $result = blade(
            components: [
                'menu' => <<<'BLADE'
                    @blaze
                    @props(['color' => 'gray'])
                    <ul class="bg-{{ $color }}-100">{{ $slot }}</ul>
                    BLADE
                ,
                'propsaware' => <<<'BLADE'
                    @blaze
                    @props(['label' => 'Button'])
                    @aware(['color' => 'gray'])
                    <button class="text-{{ $color }}">{{ $label }}</button>
                    BLADE
                ,
            ],
            view: '<x-menu color="blue"><x-propsaware label="Click Me" /></x-menu>',
        );

        expect($result)->toContain('Click Me');
        expect($result)->toContain('text-blue');
    });

    it('handles multiline @aware directive', function () {
        $result = blade(
            components: [
                'test' => <<<'BLADE'
                    @blaze
                    @aware([
                        'color' => 'gray',
                        'size' => 'md',
                    ])
                    <span class="{{ $color }}-{{ $size }}">{{ $slot }}</span>
                    BLADE
                ,
            ],
            view: '<x-test>Test</x-test>',
        );

        expect($result)->toContain('gray-md');
    });

    it('handles @aware with null value passed explicitly', function () {
        $result = blade(
            components: [
                'parent' => <<<'BLADE'
                    @blaze
                    @props(['color' => null])
                    <div class="wrapper">{{ $slot }}</div>
                    BLADE
                ,
                'child' => <<<'BLADE'
                    @blaze
                    @aware(['color' => 'default-color'])
                    <span class="text-{{ $color ?? 'fallback' }}">{{ $slot }}</span>
                    BLADE
                ,
            ],
            view: '<x-parent :color="null"><x-child>Test</x-child></x-parent>',
        );

        expect($result)->toContain('text-fallback');
    });

    it('does not interfere with sibling components', function () {
        $result = blade(
            components: [
                'menu' => <<<'BLADE'
                    @blaze
                    @props(['color' => 'gray'])
                    <ul class="bg-{{ $color }}-100">{{ $slot }}</ul>
                    BLADE
                ,
                'menu-item' => <<<'BLADE'
                    @blaze
                    @aware(['color' => 'gray'])
                    <li class="text-{{ $color }}-800">{{ $slot }}</li>
                    BLADE
                ,
            ],
            view: <<<'BLADE'
                <x-menu color="purple">
                    <x-menu-item>First</x-menu-item>
                    <x-menu-item>Second</x-menu-item>
                </x-menu>
                BLADE
            ,
        );

        expect(substr_count($result, 'text-purple-800'))->toBe(2);
    });

    it('stack is properly managed with multiple nested components', function () {
        $result = blade(
            components: [
                'menu' => <<<'BLADE'
                    @blaze
                    @props(['color' => 'gray'])
                    <ul class="bg-{{ $color }}-100">{{ $slot }}</ul>
                    BLADE
                ,
                'menu-item' => <<<'BLADE'
                    @blaze
                    @aware(['color' => 'gray'])
                    <li class="text-{{ $color }}-800">{{ $slot }}</li>
                    BLADE
                ,
            ],
            view: <<<'BLADE'
                <x-menu color="red"><x-menu-item>Red Item</x-menu-item></x-menu>
                <x-menu color="blue"><x-menu-item>Blue Item</x-menu-item></x-menu>
                BLADE
            ,
        );

        expect($result)->toContain('text-red-800');
        expect($result)->toContain('text-blue-800');
    });
});

describe('invalid @aware definitions', function () {
    it('throws exception for non-array expression', function () {
        $compiler = new AwareCompiler;

        expect(fn() => $compiler->compile('"not an array"'))
            ->toThrow(InvalidAwareDefinitionException::class);
    });

    it('throws exception for invalid syntax', function () {
        $compiler = new AwareCompiler;

        expect(fn() => $compiler->compile('[invalid syntax'))
            ->toThrow(InvalidAwareDefinitionException::class);
    });
});
