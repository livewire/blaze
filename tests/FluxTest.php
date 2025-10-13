<?php

describe('flux component integration', function () {
    beforeEach(function () {
        // Manually register Livewire and Flux providers for these tests
        app()->register(\Livewire\LivewireServiceProvider::class);
        app()->register(\Flux\FluxServiceProvider::class);
    });

    it('folds flux heading component with static props', function () {
        $input = '<flux:heading>Hello World</flux:heading>';
        $output = app('blaze')->compile($input);

        // Should be folded to a div (default level)
        expect($output)->toContain('<div');
        expect($output)->toContain('Hello World');
        expect($output)->toContain('data-flux-heading');
        expect($output)->not->toContain('flux:heading');
    });
});
