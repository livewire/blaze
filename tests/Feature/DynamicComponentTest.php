<?php

use Illuminate\Support\Facades\Blade;

describe('dynamic component inside blaze components', function () {
    beforeEach(function () {
        app('blade.compiler')->anonymousComponentPath(__DIR__.'/fixtures');
    });

    it('renders dynamic component with static name inside blaze component', function () {
        $result = blade(
            components: [
                'wrapper' => <<<'BLADE'
                    @blaze
                    <div class="wrapper">
                        <x-dynamic-component component="simple-button" type="submit" />
                    </div>
                    BLADE
                ,
            ],
            view: '<x-wrapper />',
        );

        expect($result)
            ->toContain('<div class="wrapper">')
            ->toContain('<button')
            ->toContain('type="submit"');
    });

    it('renders dynamic component with variable name inside blaze component', function () {
        $result = blade(
            components: [
                'wrapper' => <<<'BLADE'
                    @blaze
                    @props(['componentName'])
                    <div class="wrapper">
                        <x-dynamic-component :component="$componentName" type="button" />
                    </div>
                    BLADE
                ,
            ],
            view: '<x-wrapper component-name="simple-button" />',
        );

        expect($result)
            ->toContain('<div class="wrapper">')
            ->toContain('<button')
            ->toContain('type="button"');
    });

    it('renders dynamic component targeting another blaze component', function () {
        // Uses fixture: dynamic/target-simple.blade.php
        $result = blade(
            components: [
                'wrapper' => <<<'BLADE'
                    @blaze
                    <div class="outer">
                        <x-dynamic-component component="dynamic.target-simple" message="Hello" />
                    </div>
                    BLADE
                ,
            ],
            view: '<x-wrapper />',
        );

        expect($result)
            ->toContain('<div class="outer">')
            ->toContain('<span class="target">Hello</span>');
    });

    it('renders dynamic component with slot content', function () {
        // Uses fixture: dynamic/target-with-slot.blade.php
        $result = blade(
            components: [
                'wrapper' => <<<'BLADE'
                    @blaze
                    <div class="wrapper">
                        <x-dynamic-component component="dynamic.target-with-slot">
                            Slot Content Here
                        </x-dynamic-component>
                    </div>
                    BLADE
                ,
            ],
            view: '<x-wrapper />',
        );

        expect($result)
            ->toContain('<div class="wrapper">')
            ->toContain('<div class="receiver">')
            ->toContain('Slot Content Here');
    });

    it('renders dynamic component with named slots', function () {
        // Uses fixture: dynamic/target-card.blade.php
        $result = blade(
            components: [
                'wrapper' => <<<'BLADE'
                    @blaze
                    <div class="wrapper">
                        <x-dynamic-component component="dynamic.target-card">
                            <x-slot:header>Card Header</x-slot:header>
                            Card Body
                        </x-dynamic-component>
                    </div>
                    BLADE
                ,
            ],
            view: '<x-wrapper />',
        );

        expect($result)
            ->toContain('<div class="card-header">Card Header</div>')
            ->toContain('<div class="card-body">')
            ->toContain('Card Body');
    });

    it('passes attributes through to dynamic component target', function () {
        $result = blade(
            components: [
                'wrapper' => <<<'BLADE'
                    @blaze
                    <x-dynamic-component component="simple-button" class="btn-primary" data-test="value" />
                    BLADE
                ,
            ],
            view: '<x-wrapper />',
        );

        expect($result)
            ->toContain('class="btn-primary"')
            ->toContain('data-test="value"');
    });
});

describe('dynamic component compilation', function () {
    beforeEach(function () {
        app('blade.compiler')->anonymousComponentPath(__DIR__.'/fixtures');
    });

    it('does not compile dynamic-component as blaze function call', function () {
        $result = app('blaze')->compile('@blaze
<x-dynamic-component :component="$name" />');

        // Should NOT contain Blaze-specific compilation
        expect($result)->not->toContain('$__blaze->ensureCompiled');
        expect($result)->not->toContain('$__blaze->resolve');

        // Should pass through to Blade (contains @blaze but dynamic-component is unchanged in Blaze layer)
        expect($result)->toContain('@blaze');
    });

    it('leaves dynamic-component tag for blade to process', function () {
        // When Blaze compiles a template with <x-dynamic-component>,
        // it should leave it unchanged for Blade's ComponentTagCompiler to handle
        $result = app('blaze')->compile('@blaze
<div><x-dynamic-component component="foo" /></div>');

        // Blaze compilation should not transform the dynamic-component tag
        // The tag will be processed later by Blade's standard compiler
        expect($result)->not->toContain('_'.hash('xxh128', 'v2'));
    });
});
