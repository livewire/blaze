<?php

use Illuminate\Support\Facades\Artisan;
use Livewire\Blaze\BladeService;
use Livewire\Blaze\Blaze;
use Livewire\Blaze\BlazeManager;

/**
 * This test ensures Blaze never resolves blade compiler from the container.
 * 
 * During `php artisan optimize`, Laravel bootstraps a fresh application instance
 * in `config:cache`, but later in `view:cache` it uses the original instance
 * to compile the views. Now, when we resolve the blade compiler anywhere,
 * it returns a different instance than the one compiling the view.
 * 
 * This only happens if blade engine (which uses the compiler as a dependency)
 * is resolved during boot by a package. Sentry does this to decorate it.
 * Normally the compiler would get resolved from the new app instance.
 * 
 * Blaze heavily depends on the blade compiler and its internal state.
 * Since we can't trust the container, these tests guarantee that
 * we never use it to resolve the compiler. We must always use
 * the instance that registered the precompilation hooks.
 */

beforeEach(function () {
    Artisan::call('view:clear');
});

test('doesnt resolve blade compiler from the container', function () {
    $compiler = app('blade.compiler');

    Blaze::clearResolvedInstance();

    app()->forgetInstance(BlazeManager::class);
    app()->forgetInstance(BladeService::class);
    app()->forgetInstance('blade.compiler');
    
    app()->resolving('blade.compiler', function () {
        test()->fail('Blade compiler was resolved from container');
    });
    
    $compiler->compile(fixture_path('views/blaze.blade.php'));
})->throwsNoExceptions();

test('doesnt resolve blade compiler from the container when using debug mode', function () {
    Blaze::debug();

    $compiler = app('blade.compiler');

    Blaze::clearResolvedInstance();

    app()->forgetInstance(BlazeManager::class);
    app()->forgetInstance(BladeService::class);
    app()->forgetInstance('blade.compiler');
    
    app()->resolving('blade.compiler', function () {
        test()->fail('Blade compiler was resolved from container');
    });

    $compiler->compile(fixture_path('views/blaze.blade.php'));
})->throwsNoExceptions();

test('doesnt resolve blade compiler from the container when using debug mode with blaze off', function () {
    Blaze::debug();
    Blaze::disable();
    
    $compiler = app('blade.compiler');

    Blaze::clearResolvedInstance();

    app()->forgetInstance(BlazeManager::class);
    app()->forgetInstance(BladeService::class);
    app()->forgetInstance('blade.compiler');
    
    app()->resolving('blade.compiler', function () {
        test()->fail('Blade compiler was resolved from container');
    });

    $compiler->compile(fixture_path('views/blaze.blade.php'));
})->throwsNoExceptions();
