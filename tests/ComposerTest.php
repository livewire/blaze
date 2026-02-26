<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;

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
