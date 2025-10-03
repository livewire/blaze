<?php

describe('infinite recursion bugs', function () {
    beforeEach(function () {
        // Configure Blade to find our test components and views
        app('blade.compiler')->anonymousComponentNamespace('', 'x');
        app('blade.compiler')->anonymousComponentPath(__DIR__ . '/fixtures/components');
    });

    it('should handle dynamic attributes without infinite recursion', function () {
        $template = '<x-button type="submit" class="btn-primary">Submit</x-button>';

        // This should complete without hanging or crashing
        $rendered = \Illuminate\Support\Facades\Blade::render($template);

        expect($rendered)->toContain('<button');
        expect($rendered)->toContain('type="submit"');
        expect($rendered)->toContain('class="btn-primary"');
        expect($rendered)->toContain('Submit');
        expect($rendered)->not->toContain('<x-button');
    });

    it('should handle nested pure components without infinite recursion', function () {
        $template = '<x-card><x-alert message="Nested alert!" /></x-card>';

        // This should complete without hanging or crashing
        $rendered = \Illuminate\Support\Facades\Blade::render($template);

        expect($rendered)->toContain('<div class="card">');
        expect($rendered)->toContain('<div class="alert">');
        expect($rendered)->toContain('Nested alert!');
        expect($rendered)->not->toContain('<x-card>');
        expect($rendered)->not->toContain('<x-alert');
    });

    it('should handle complex nested structure without infinite recursion', function () {
        $template = '
        <x-card>
            <x-button type="button">Button 1</x-button>
            <x-alert message="Alert in card" />
            <x-button type="submit" class="secondary">Button 2</x-button>
        </x-card>';

        // This should complete without hanging or crashing  
        $rendered = \Illuminate\Support\Facades\Blade::render($template);

        expect($rendered)->toContain('<div class="card">');
        expect($rendered)->toContain('Button 1');
        expect($rendered)->toContain('Button 2');
        expect($rendered)->toContain('Alert in card');
        expect($rendered)->not->toContain('<x-card>');
        expect($rendered)->not->toContain('<x-button');
        expect($rendered)->not->toContain('<x-alert');
    });

    it('should handle multiple levels of nesting without infinite recursion', function () {
        // Create a more complex nesting scenario
        $template = '
        <div class="wrapper">
            <x-card>
                <h1>Outer Card</h1>
                <x-card>
                    <h2>Inner Card</h2>
                    <x-button type="button">Nested Button</x-button>
                    <x-alert message="Deeply nested alert" />
                </x-card>
                <x-button type="submit">Outer Button</x-button>
            </x-card>
        </div>';

        // This should complete without hanging or crashing
        $rendered = \Illuminate\Support\Facades\Blade::render($template);

        expect($rendered)->toContain('<div class="wrapper">');
        expect($rendered)->toContain('<h1>Outer Card</h1>');
        expect($rendered)->toContain('<h2>Inner Card</h2>');
        expect($rendered)->toContain('Nested Button');
        expect($rendered)->toContain('Deeply nested alert');
        expect($rendered)->toContain('Outer Button');
        expect($rendered)->not->toContain('<x-card>');
        expect($rendered)->not->toContain('<x-button');
        expect($rendered)->not->toContain('<x-alert');
    });

    it('should handle components with variable attributes without infinite recursion', function () {
        // Test components with dynamic/variable attributes that might trigger edge cases
        $template = '
        <x-button 
            type="submit" 
            class="btn-primary btn-large"
            data-action="save"
            aria-label="Save changes">
            Save Changes
        </x-button>';

        // This should complete without hanging or crashing
        $rendered = \Illuminate\Support\Facades\Blade::render($template);

        expect($rendered)->toContain('<button');
        expect($rendered)->toContain('type="submit"');
        expect($rendered)->toContain('class="btn-primary btn-large"');
        expect($rendered)->toContain('data-action="save"');
        expect($rendered)->toContain('aria-label="Save changes"');
        expect($rendered)->toContain('Save Changes');
        expect($rendered)->not->toContain('<x-button');
    });

    it('should handle edge case with quotes and special characters', function () {
        // Test edge case that might trigger recursion issues
        $template = '<x-button class="btn \'quoted\' &amp; escaped" data-test=\'{"key": "value"}\'>Complex</x-button>';

        // This should complete without hanging or crashing
        $rendered = \Illuminate\Support\Facades\Blade::render($template);

        expect($rendered)->toContain('<button');
        expect($rendered)->toContain('Complex');
        expect($rendered)->not->toContain('<x-button');
    });
});
