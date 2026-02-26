<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\View\View as ConcreteView;

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
