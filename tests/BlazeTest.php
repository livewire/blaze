<?php

use Illuminate\Support\Facades\View;

describe('blaze integration', function () {
    beforeEach(function () {
        // Configure Blade to find our test components and views
        app('blade.compiler')->anonymousComponentNamespace('', 'x');
        app('blade.compiler')->anonymousComponentPath(__DIR__ . '/fixtures/components');

        // Add view path for our test pages
        View::addLocation(__DIR__ . '/fixtures/pages');
    });

    it('renders pure components as optimized html through blade facade', function () {
        // This is still outside-in - we're using Laravel's Blade facade, not calling Blaze directly
        $template = '
        <div class="page">
            <h1>Integration Test</h1>
            <x-button>Save Changes</x-button>
            <x-card>
                <x-alert message="Success!" />
            </x-card>
        </div>';

        $rendered = \Illuminate\Support\Facades\Blade::render($template);

        // Pure components should be folded to optimized HTML
        expect($rendered)->toContain('<button type="button">Save Changes</button>');
        expect($rendered)->toContain('<div class="card">');
        expect($rendered)->toContain('<div class="alert">Success!</div>');

        expect($rendered)->not->toContain('<x-button>');
        expect($rendered)->not->toContain('<x-card>');
        expect($rendered)->not->toContain('<x-alert');

        // Should contain the page structure
        expect($rendered)->toContain('<div class="page">');
        expect($rendered)->toContain('<h1>Integration Test</h1>');
    });

    it('leaves non-pure components unchanged through blade facade', function () {
        $template = '<div><x-impure-button>Test Button</x-impure-button></div>';
        $rendered = \Illuminate\Support\Facades\Blade::render($template);

        // Non-pure component should render normally (not folded)
        expect($rendered)->toContain('<button type="button">Test Button</button>');
        expect($rendered)->not->toContain('<x-impure-button>');

        // Just verify it renders normally
    });

    it('throws exception for components with invalid pure usage', function () {
        expect(fn() => \Illuminate\Support\Facades\Blade::render('<x-invalid-pure>Test</x-invalid-pure>'))
            ->toThrow(\Livewire\Blaze\Exceptions\InvalidPureUsageException::class);
    });

    it('preserves slot content when folding components', function () {
        $template = '<x-card><h2>Dynamic Title</h2><p>Dynamic content with <em>emphasis</em></p></x-card>';
        $rendered = \Illuminate\Support\Facades\Blade::render($template);

        // Should preserve all slot content in folded output
        expect($rendered)->toContain('<h2>Dynamic Title</h2>');
        expect($rendered)->toContain('<p>Dynamic content with <em>emphasis</em></p>');
        expect($rendered)->toContain('<div class="card">');
        expect($rendered)->not->toContain('<x-card>');
    });

});
