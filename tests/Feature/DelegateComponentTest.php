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
        // The delegate component is rendered AFTER slots are compiled,
        // so it can access parent slots via @aware
        $result = blade(
            components: [
                'outer' => <<<'BLADE'
                    @blaze
                    <flux:delegate-component :component="'inner'">
                        <x-slot:header>Custom Header</x-slot:header>
                        Body content
                    </flux:delegate-component>
                    BLADE
                ,
            ],
            view: '<x-outer />',
        );

        // The delegate target receives the slots
        expect($result)->toContain('Custom Header');
        expect($result)->toContain('Body content');
    });
});
