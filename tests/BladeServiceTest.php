<?php

use App\View\Components\Alert;
use Illuminate\Support\Facades\Blade;
use Livewire\Blaze\BladeService;

test('componentNameToPath', function ($callback, $prefix, $name, $path) {
    $callback();

    expect(app(BladeService::class)->componentNameToPath($prefix . $name))->toBe($path);
})
->with([
    'default path' => [fn () => null, 'dummy.'],
    'custom path' => [fn () => Blade::anonymousComponentPath(fixture_path('views/components/dummy')), ''],
    'custom path with prefix' => [fn () => Blade::anonymousComponentPath(fixture_path('views/components/dummy'), 'dummy'), 'dummy::'],
    'custom namespace' => [fn () => Blade::anonymousComponentNamespace('components.dummy', 'dummy'), 'dummy::'],
])
->with([
    'exact file' => ['foo', fixture_path('views/components/dummy/foo.blade.php')],
    'index file' => ['bar', fixture_path('views/components/dummy/bar/index.blade.php')],
    'directory name' => ['baz', fixture_path('views/components/dummy/baz/baz.blade.php')],
]);

test('componentNameToPath skips class-based component registered as alias', function () {
    Blade::component('alert', Alert::class);

    expect(app(BladeService::class)->componentNameToPath('alert'))->toBe('');
});

test('componentNameToPath skips class-based component registered via namespace', function () {
    Blade::componentNamespace('App\\View\\Components', 'app');

    expect(app(BladeService::class)->componentNameToPath('app::alert'))->toBe('');
});

test('componentNameToPath skips class-based component found via app namespace convention', function () {
    // Alert maps to App\View\Components\Alert in workbench.
    expect(app(BladeService::class)->componentNameToPath('alert'))->toBe('');
});

test('componentNameToPath resolves view-based component alias', function () {
    Blade::component('components.input', 'my-input');

    expect(app(BladeService::class)->componentNameToPath('my-input'))
        ->toBe(fixture_path('views/components/input.blade.php'));
});
