<?php

use Illuminate\Support\Facades\Blade;
use Livewire\Blaze\BladeService;

test('componentNameToPath', function ($callback, $prefix, $name, $path) {
    $callback();

    expect(app(BladeService::class)->componentNameToPath($prefix . $name))->toBe($path);
})
->with([
    'default path' => [fn () => null, 'dummy.'],
    'custom path' => [fn () => Blade::anonymousComponentPath(fixture_path('components/dummy')), ''],
    'custom path with prefix' => [fn () => Blade::anonymousComponentPath(fixture_path('components/dummy'), 'dummy'), 'dummy::'],
    // 'custom namespace' => [fn () => Blade::anonymousComponentNamespace('dummy'), 'dummy::'],
])
->with([
    'exact file' => ['foo', fixture_path('components/dummy/foo.blade.php')],
    'index file' => ['bar', fixture_path('components/dummy/bar/index.blade.php')],
    'directory name' => ['baz', fixture_path('components/dummy/baz/baz.blade.php')],
]);