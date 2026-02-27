<?php

use Illuminate\Support\Facades\View;

test('it supports View::composer() for blaze components', function () {
    View::composer('test-composer-view', function ($view) {
        $view->with('composedVariable', 'composed value');
    });

    $view = <<<'BLADE'
@blaze(name: 'test-composer-view')
<div>{{ $composedVariable }}</div>
BLADE;

    $output = blade(
        view: '<x-test-composer />',
        components: [
            'test-composer' => $view,
        ]
    );

    expect(trim($output))->toBe('<div>composed value</div>');
});

test('view composers can modify existing data', function () {
    View::composer('test-modify-view', function ($view) {
        $data = $view->getData();
        $view->with('count', ($data['count'] ?? 0) + 1);
    });

    $view = <<<'BLADE'
@blaze(name: 'test-modify-view')
@props(['count' => 0])
<div>Count: {{ $count }}</div>
BLADE;

    $output = blade(
        view: '<x-test-modify :count="10" />',
        components: [
            'test-modify' => $view,
        ]
    );

    // Count: 11 (10 from props + 1 from composer)
    expect(trim($output))->toBe('<div>Count: 11</div>');
});

test('multiple composers can be applied', function () {
    View::composer('test-multi-view', function ($view) {
        $view->with('a', 'A');
    });

    View::composer('test-multi-view', function ($view) {
        $view->with('b', 'B');
    });

    $view = <<<'BLADE'
@blaze(name: 'test-multi-view')
<div>{{ $a }}{{ $b }}</div>
BLADE;

    $output = blade(
        view: '<x-test-multi />',
        components: [
            'test-multi' => $view,
        ]
    );

    expect(trim($output))->toBe('<div>AB</div>');
});
