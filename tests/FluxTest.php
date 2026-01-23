<?php

use Illuminate\Support\Facades\Route;

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

    it('folds link component with dynamic route helper link', function () {
        Route::get('/dashboard', fn () => 'dashboard')->name('dashboard');

        $input = '<flux:link :href="route(\'dashboard\')">Dashboard</flux:link>';
        $output = app('blaze')->compile($input);

        // Should be folded (contain the anchor tag)
        expect($output)->toContain('<a ');

        // Dynamic attributes use boolean fencing pattern
        expect($output)->toContain('$__blazeAttr = route(\'dashboard\')');
        expect($output)->toContain('href="');

        // Should NOT be function compiled
        expect($output)->not->toContain('$__blaze->ensureCompiled');
    });
});
