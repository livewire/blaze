<?php

use Illuminate\Support\Facades\Blade;

describe('delegate component rendered output', function () {
    beforeEach(function () {
        // Register flux:: namespace pointing to our test fixtures
        app('blade.compiler')->anonymousComponentPath(__DIR__ . '/fixtures/flux', 'flux');
    });

    it('renders delegate component with default variant', function () {
        $result = Blade::render('<flux:button>Click Me</flux:button>');

        expect($result)
            ->toContain('<button')
            ->toContain('btn-default')
            ->toContain('Click Me');
    });

    it('renders delegate component with specified variant', function () {
        $result = Blade::render('<flux:button variant="primary">Submit</flux:button>');

        expect($result)
            ->toContain('<button')
            ->toContain('btn-primary')
            ->toContain('Submit');
    });

    it('passes props through to delegated component', function () {
        $result = Blade::render('<flux:button variant="primary" size="lg">Large Button</flux:button>');

        expect($result)
            ->toContain('btn-primary')
            ->toContain('btn-lg')
            ->toContain('Large Button');
    });

    it('renders self-closing delegate component', function () {
        $result = Blade::render('<flux:button variant="default" />');

        expect($result)
            ->toContain('<button')
            ->toContain('btn-default');
    });
});

describe('delegate component slot forwarding', function () {
    beforeEach(function () {
        app('blade.compiler')->anonymousComponentPath(__DIR__ . '/fixtures/flux', 'flux');
    });

    it('forwards named slots to delegate target', function () {
        $result = Blade::render('
            <flux:select>
                <x-slot:trigger>Custom Trigger</x-slot:trigger>
                <option>Option 1</option>
            </flux:select>
        ');

        expect($result)
            ->toContain('select-default')
            ->toContain('<div class="trigger">Custom Trigger</div>')
            ->toContain('<option>Option 1</option>');
    });

    it('forwards default slot to delegate target', function () {
        $result = Blade::render('
            <flux:select>
                <option>First</option>
                <option>Second</option>
            </flux:select>
        ');

        expect($result)
            ->toContain('<option>First</option>')
            ->toContain('<option>Second</option>');
    });

    it('passes props and forwards slots together', function () {
        $result = Blade::render('
            <flux:select placeholder="Choose...">
                <x-slot:trigger>Pick One</x-slot:trigger>
                <option>A</option>
            </flux:select>
        ');

        expect($result)
            ->toContain('<option value="" disabled>Choose...</option>')
            ->toContain('<div class="trigger">Pick One</div>')
            ->toContain('<option>A</option>');
    });
});

describe('delegate component @aware with slots', function () {
    beforeEach(function () {
        app('blade.compiler')->anonymousComponentPath(__DIR__ . '/fixtures/flux', 'flux');
        app('blade.compiler')->anonymousComponentPath(__DIR__ . '/fixtures');
    });

    it('delegate target can access parent slots via @aware', function () {
        $result = blade(
            components: [
                'outer' => <<<'BLADE'
                    @blaze
                    <flux:delegate-component :component="'delegate-target-slot'">
                        Body content
                    </flux:delegate-component>
                    BLADE
                ,
            ],
            view: <<<'BLADE'
                <x-outer>
                    <x-slot:header>Custom Header</x-slot:header>
                </x-outer>
                BLADE
            ,
        );

        // The delegate target receives the slots
        expect($result)->toContain('Custom Header');
        expect($result)->toContain('Body content');
    });
});

describe('delegate component @aware data propagation', function () {
    beforeEach(function () {
        app('blade.compiler')->anonymousComponentPath(__DIR__ . '/fixtures/flux', 'flux');
    });

    it('delegate-component pushes data so delegate target can @aware parent props', function () {
        // The delegate-aware-parent component receives variant="primary" as a prop,
        // then uses <flux:delegate-component> to render delegate-aware-target.
        // The delegate target uses @aware(['variant']) to read variant from the data stack.
        // Without pushData/popData wrapping the delegate call, the target would
        // fall back to the default ('default') instead of seeing 'primary'.
        $result = Blade::render('<flux:delegate-aware-parent variant="primary">Content</flux:delegate-aware-parent>');

        expect($result)
            ->toContain('target-primary')
            ->not->toContain('target-default');
    });

    it('delegate-component @aware falls back to default when no parent provides value', function () {
        $result = Blade::render('<flux:delegate-aware-parent>Content</flux:delegate-aware-parent>');

        expect($result)->toContain('target-default');
    });
});

describe('delegate component with forwarded attributes', function () {
    beforeEach(function () {
        app('blade.compiler')->anonymousComponentPath(__DIR__ . '/fixtures/flux', 'flux');
        app('blade.compiler')->anonymousComponentPath(__DIR__ . '/fixtures');
    });

    it('delegate target does not inherit attributes from parents', function () {
        // When a parent passes :attributes to a child that uses flux:delegate-component,
        // the delegate target should NOT receive the ComponentAttributeBag as 'attributes'.
        // Instead, it should get a fresh $attributes bag for its own use.
        $result = Blade::render('<flux:delegate-attrs-outer data-outside />');

        expect($result)->not->toContain('data-outside');
        expect($result)->not->toContain('data-outer');
        expect($result)->toContain('data-middle');
        expect($result)->toContain('data-inner');
        expect($result)->toContain('data-wrapper');
    });
});
