<?php

beforeEach(function () {
    app('blade.compiler')->anonymousComponentPath(__DIR__ . '/fixtures');
});

describe('props with defaults', function () {
    it('uses default when prop not passed', function () {
        $result = blade(
            components: [
                'button' => <<<'BLADE'
                    @blaze
                    @props(['type' => 'button'])
                    <button type="{{ $type }}">Click</button>
                    BLADE
                ,
            ],
            view: '<x-button />',
        );

        expect($result)->toContain('type="button"');
    });

    it('uses passed value over default', function () {
        $result = blade(
            components: [
                'button' => <<<'BLADE'
                    @blaze
                    @props(['type' => 'button'])
                    <button type="{{ $type }}">Click</button>
                    BLADE
                ,
            ],
            view: '<x-button type="submit" />',
        );

        expect($result)->toContain('type="submit"');
    });

    it('uses dynamic passed value over default', function () {
        $result = blade(
            components: [
                'button' => <<<'BLADE'
                    @blaze
                    @props(['type' => 'button'])
                    <button type="{{ $type }}">Click</button>
                    BLADE
                ,
            ],
            view: '<x-button :type="$buttonType" />',
            data: ['buttonType' => 'reset'],
        );

        expect($result)->toContain('type="reset"');
    });

    it('uses default when explicit null is passed (matches Laravel behavior)', function () {
        $result = blade(
            components: [
                'button' => <<<'BLADE'
                    @blaze
                    @props(['type' => 'button'])
                    <button type="{{ $type }}">Click</button>
                    BLADE
                ,
            ],
            view: '<x-button :type="$nullType" />',
            data: ['nullType' => null],
        );

        expect($result)->toContain('type="button"');
    });
});

describe('falsy values override defaults', function () {
    it('false overrides true default', function () {
        $result = blade(
            components: [
                'button' => <<<'BLADE'
                    @blaze
                    @props(['disabled' => true])
                    <button {{ $disabled ? 'disabled' : '' }}>Click</button>
                    BLADE
                ,
            ],
            view: '<x-button :disabled="$isDisabled" />',
            data: ['isDisabled' => false],
        );

        expect($result)->not->toContain('disabled');
    });

    it('true overrides false default', function () {
        $result = blade(
            components: [
                'button' => <<<'BLADE'
                    @blaze
                    @props(['disabled' => false])
                    <button {{ $disabled ? 'disabled' : '' }}>Click</button>
                    BLADE
                ,
            ],
            view: '<x-button :disabled="$isDisabled" />',
            data: ['isDisabled' => true],
        );

        expect($result)->toContain('disabled');
    });

    it('empty string overrides string default', function () {
        $result = blade(
            components: [
                'button' => <<<'BLADE'
                    @blaze
                    @props(['type' => 'button'])
                    <button type="{{ $type }}">Click</button>
                    BLADE
                ,
            ],
            view: '<x-button type="" />',
        );

        expect($result)->toContain('type=""');
    });

    it('zero overrides non-zero default', function () {
        $result = blade(
            components: [
                'counter' => <<<'BLADE'
                    @blaze
                    @props(['count' => 10])
                    <span>{{ $count }}</span>
                    BLADE
                ,
            ],
            view: '<x-counter :count="0" />',
        );

        expect($result)->toContain('<span>0</span>');
    });
});

describe('required props (numeric keys)', function () {
    it('sets variable when prop is passed', function () {
        $result = blade(
            components: [
                'label' => <<<'BLADE'
                    @blaze
                    @props(['label'])
                    <span>{{ $label ?? 'undefined' }}</span>
                    BLADE
                ,
            ],
            view: '<x-label label="Hello" />',
        );

        expect($result)->toContain('<span>Hello</span>');
    });

    it('leaves variable undefined when prop not passed', function () {
        $result = blade(
            components: [
                'label' => <<<'BLADE'
                    @blaze
                    @props(['label'])
                    <span>{{ $label ?? 'undefined' }}</span>
                    BLADE
                ,
            ],
            view: '<x-label />',
        );

        expect($result)->toContain('<span>undefined</span>');
    });

    it('sets variable to null when null is explicitly passed', function () {
        $result = blade(
            components: [
                'label' => <<<'BLADE'
                    @blaze
                    @props(['label'])
                    <span>{{ $label ?? 'undefined' }}</span>
                    BLADE
                ,
            ],
            view: '<x-label :label="$nullLabel" />',
            data: ['nullLabel' => null],
        );

        expect($result)->toContain('<span>undefined</span>');
    });
});

describe('kebab-case to camelCase conversion', function () {
    it('converts kebab-case attribute to camelCase prop', function () {
        $result = blade(
            components: [
                'box' => <<<'BLADE'
                    @blaze
                    @props(['backgroundColor' => 'white'])
                    <div style="background-color: {{ $backgroundColor }}">Content</div>
                    BLADE
                ,
            ],
            view: '<x-box background-color="red" />',
        );

        expect($result)->toContain('background-color: red');
    });

    it('accepts camelCase attribute directly', function () {
        $result = blade(
            components: [
                'box' => <<<'BLADE'
                    @blaze
                    @props(['backgroundColor' => 'white'])
                    <div style="background-color: {{ $backgroundColor }}">Content</div>
                    BLADE
                ,
            ],
            view: '<x-box backgroundColor="blue" />',
        );

        expect($result)->toContain('background-color: blue');
    });
});

describe('attributes bag filtering', function () {
    it('removes props from attributes bag', function () {
        $result = blade(
            components: [
                'button' => <<<'BLADE'
                    @blaze
                    @props(['type' => 'button'])
                    <button type="{{ $type }}" {{ $attributes }}>Click</button>
                    BLADE
                ,
            ],
            view: '<x-button type="submit" class="btn" />',
        );

        expect($result)->toContain('class="btn"');
        expect($result)->toContain('type="submit"');
    });

    it('removes both camelCase and kebab-case from attributes', function () {
        $result = blade(
            components: [
                'box' => <<<'BLADE'
                    @blaze
                    @props(['backgroundColor' => 'white'])
                    <div style="background-color: {{ $backgroundColor }}" {{ $attributes }}>Content</div>
                    BLADE
                ,
            ],
            view: '<x-box background-color="red" class="test" />',
        );

        $attributesOutput = preg_match('/<div[^>]*class="([^"]*)"/', $result, $matches);
        expect($attributesOutput)->toBe(1);
        expect($matches[1])->toBe('test');
    });
});

describe('cross-prop references in defaults (Laravel behavioral parity)', function () {
    it('does not allow cross-prop references (matches Laravel behavior)', function () {
        $result = blade(
            components: [
                'test' => <<<'BLADE'
                    @blaze
                    @props(['first' => 'hello', 'second' => $first ?? 'fallback'])
                    <span>{{ $second }}</span>
                    BLADE
                ,
            ],
            view: '<x-test />',
        );

        expect($result)->toContain('<span>fallback</span>');
    });

    it('evaluates all defaults before any prop assignment', function () {
        $result = blade(
            components: [
                'test' => <<<'BLADE'
                    @blaze
                    @props(['a' => 'A', 'b' => $a ?? 'B', 'c' => $b ?? 'C'])
                    <span>{{ $a }}-{{ $b }}-{{ $c }}</span>
                    BLADE
                ,
            ],
            view: '<x-test />',
        );

        expect($result)->toContain('<span>A-B-C</span>');
    });

    it('allows passed values to override cross-reference defaults', function () {
        $result = blade(
            components: [
                'test' => <<<'BLADE'
                    @blaze
                    @props(['first' => 'hello', 'second' => $first ?? 'fallback'])
                    <span>{{ $first }}-{{ $second }}</span>
                    BLADE
                ,
            ],
            view: '<x-test second="custom" />',
        );

        expect($result)->toContain('<span>hello-custom</span>');
    });
});

describe('$attributes in @props defaults', function () {
    it('allows using $attributes in prop defaults', function () {
        $result = blade(
            components: [
                'button' => <<<'BLADE'
                    @blaze
                    @props(['type' => $attributes->whereStartsWith('type')->first()])
                    <button type="{{ $type }}">Click</button>
                    BLADE
                ,
            ],
            view: '<x-button type="submit" />',
        );

        expect($result)->toContain('type="submit"');
    });

    it('works with $attributes->get() for defaults', function () {
        $result = blade(
            components: [
                'button' => <<<'BLADE'
                    @blaze
                    @props(['variant' => $attributes->get('data-variant', 'primary')])
                    <button class="btn-{{ $variant }}">Click</button>
                    BLADE
                ,
            ],
            view: '<x-button />',
        );

        expect($result)->toContain('class="btn-primary"');
    });

    it('works with $attributes->get() extracting passed values', function () {
        $result = blade(
            components: [
                'button' => <<<'BLADE'
                    @blaze
                    @props(['variant' => $attributes->get('data-variant', 'primary')])
                    <button class="btn-{{ $variant }}">Click</button>
                    BLADE
                ,
            ],
            view: '<x-button data-variant="secondary" />',
        );

        expect($result)->toContain('class="btn-secondary"');
    });

    it('works with $attributes->has() for conditional defaults', function () {
        $template = <<<'BLADE'
            @blaze
            @props(['size' => $attributes->has('large') ? 'lg' : 'md'])
            <button class="btn-{{ $size }}" {{ $attributes->except('large') }}>Click</button>
            BLADE;

        $result = blade(
            components: ['button' => $template],
            view: '<x-button />',
        );
        expect($result)->toContain('class="btn-md"');

        $result = blade(
            components: ['button' => $template],
            view: '<x-button large />',
        );
        expect($result)->toContain('class="btn-lg"');
    });
});

describe('edge cases', function () {
    it('works with component with @blaze but no @props', function () {
        $result = blade(
            components: [
                'button' => <<<'BLADE'
                    @blaze
                    <button {{ $attributes }}>Click</button>
                    BLADE
                ,
            ],
            view: '<x-button class="btn" />',
        );

        expect($result)->toContain('class="btn"');
        expect($result)->toContain('Click');
    });

    it('extracts all passed data as variables when no @props directive', function () {
        $result = blade(
            components: [
                'test' => <<<'BLADE'
                    @blaze
                    <span>{{ $size }}-{{ $variant }}</span>
                    BLADE
                ,
            ],
            view: '<x-test size="lg" variant="primary" />',
        );

        expect($result)->toContain('<span>lg-primary</span>');
    });

    it('works with empty @props array', function () {
        $result = blade(
            components: [
                'box' => <<<'BLADE'
                    @blaze
                    @props([])
                    <div {{ $attributes }}>Content</div>
                    BLADE
                ,
            ],
            view: '<x-box class="test" />',
        );

        expect($result)->toContain('class="test"');
    });

    it('handles multiline @props directive', function () {
        $result = blade(
            components: [
                'card' => <<<'BLADE'
                    @blaze
                    @props([
                        'title' => 'Default Title',
                        'subtitle',
                        'showFooter' => true,
                    ])
                    <div>{{ $title }}</div>
                    BLADE
                ,
            ],
            view: '<x-card />',
        );

        expect($result)->toContain('Default Title');
    });

    it('handles closure defaults', function () {
        $result = blade(
            components: [
                'test' => <<<'BLADE'
                    @blaze
                    @props(['callback' => fn() => 'default-value'])
                    <span>{{ $callback() }}</span>
                    BLADE
                ,
            ],
            view: '<x-test />',
        );

        expect($result)->toContain('default-value');
    });

    it('handles expression defaults like now()', function () {
        $result = blade(
            components: [
                'test' => <<<'BLADE'
                    @blaze
                    @props(['timestamp' => now()])
                    <span>{{ $timestamp instanceof \Carbon\Carbon ? 'yes' : 'no' }}</span>
                    BLADE
                ,
            ],
            view: '<x-test />',
        );

        expect($result)->toContain('yes');
    });

    it('handles array defaults', function () {
        $result = blade(
            components: [
                'test' => <<<'BLADE'
                    @blaze
                    @props(['items' => ['a', 'b', 'c']])
                    <span>{{ count($items) }}</span>
                    BLADE
                ,
            ],
            view: '<x-test />',
        );

        expect($result)->toContain('<span>3</span>');
    });
});
