<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Illuminate\View\View as ConcreteView;

beforeEach(function () {
    config()->set('blaze.view_composers', true);

    foreach ([
        fixture_path('components/header.blade.php'),
        fixture_path('components/header-disabled.blade.php'),
        fixture_path('components/nav/index.blade.php'),
        fixture_path('components/badge/badge.blade.php'),
    ] as $path) {
        $compiledPath = app('blade.compiler')->getCompiledPath($path);

        if (File::exists($compiledPath)) {
            File::delete($compiledPath);
        }
    }
});

class TestHeaderComposer
{
    public function compose(ConcreteView $view): void
    {
        $view->with(['title' => 'From Class Composer']);
    }
}

test('default props are shown when no composer is registered', function () {
    $output = Blade::render('<x-header />');

    expect($output)->toContain('Default Title');
});

test('composers are not fired when composer support is disabled', function () {
    config()->set('blaze.view_composers', false);

    View::composer('header-disabled', fn ($view) => $view->with(['title' => 'Injected by Composer']));

    $output = Blade::render('<x-header-disabled />');

    expect($output)->toContain('Default Disabled Title')
        ->and($output)->not->toContain('Injected by Composer');
});

test('view composer injects data into component when no explicit prop is passed', function () {
    View::composer('header', fn ($view) => $view->with(['title' => 'Injected by Composer']));

    $output = Blade::render('<x-header />');

    expect($output)->toContain('Injected by Composer');
});

test('view composer takes precedence over explicit prop', function () {
    View::composer('header', fn ($view) => $view->with(['title' => 'From Composer', 'subtitle' => 'From Composer']));

    $output = Blade::render('<x-header title="Explicitly Passed" />');

    expect($output)->toContain('From Composer - From Composer')
        ->and($output)->not->toContain('Explicitly Passed');
});

test('composer receives existing component data via getData()', function () {
    View::composer('header', function ($view) {
        if ($view->getData()['title'] === 'Special') {
            $view->with('subtitle', 'Conditionally Set');
        }
    });

    $with    = Blade::render('<x-header title="Special" />');
    $without = Blade::render('<x-header title="Other" />');

    expect($with)->toContain('Special - Conditionally Set')
        ->and($without)->toContain('Other - Default Subtitle');
});

test('wildcard composer applies to all blaze components', function () {
    View::composer('*', fn ($view) => $view->with(['title' => 'From Wildcard']));

    $output = Blade::render('<x-header />');

    expect($output)->toContain('From Wildcard');
});

test('composer closure may type-hint the concrete View class', function () {
    View::composer('header', function (ConcreteView $view) {
        $view->with(['title' => 'From Typed Closure']);
    });

    $output = Blade::render('<x-header />');

    expect($output)->toContain('From Typed Closure');
});

test('class-based composer is resolved from the container', function () {
    View::composer('header', TestHeaderComposer::class);

    $output = Blade::render('<x-header />');

    expect($output)->toContain('From Class Composer');
});

test('composer fires for index-convention component (nav/index.blade.php registered as nav)', function () {
    View::composer('nav', fn ($view) => $view->with(['label' => 'Injected Nav']));

    $output = Blade::render('<x-nav />');

    expect($output)->toContain('Injected Nav');
});

test('composer fires for same-name convention component (badge/badge.blade.php registered as badge)', function () {
    View::composer('badge', fn ($view) => $view->with(['label' => 'Injected Badge']));

    $output = Blade::render('<x-badge />');

    expect($output)->toContain('Injected Badge');
});
