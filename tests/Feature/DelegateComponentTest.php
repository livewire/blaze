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
