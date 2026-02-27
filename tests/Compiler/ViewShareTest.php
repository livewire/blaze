<?php

use Illuminate\Support\Facades\View;

test('it supports View::share variables', function () {
    View::share('sharedVariable', 'shared value');

    $view = <<<'BLADE'
@blaze
<div>{{ $sharedVariable }}</div>
BLADE;

    $output = blade(
        view: '<x-test-share />',
        components: [
            'test-share' => $view,
        ]
    );

    expect(trim($output))->toBe('<div>shared value</div>');
});

test('props take precedence over View::share variables', function () {
    View::share('title', 'shared title');

    $view = <<<'BLADE'
@blaze
@props(['title' => 'default title'])
<div>{{ $title }}</div>
BLADE;

    $output = blade(
        view: '<x-test-precedence title="explicit title" />',
        components: [
            'test-precedence' => $view,
        ]
    );

    expect(trim($output))->toBe('<div>explicit title</div>');
});

test('View::share variables are available in nested components', function () {
    View::share('globalData', 'global');

    $child = <<<'BLADE'
@blaze
<span>{{ $globalData }}</span>
BLADE;

    $parent = <<<'BLADE'
@blaze
<div><x-nested-child /></div>
BLADE;

    $output = blade(
        view: '<x-nested-parent />',
        components: [
            'nested-parent' => $parent,
            'nested-child' => $child,
        ]
    );

    expect(trim($output))->toBe('<div><span>global</span></div>');
});

test('View::share supports objects and arrays', function () {
    $user = (object) ['name' => 'John'];
    View::share('sharedUser', $user);
    View::share('sharedConfig', ['debug' => true]);

    $view = <<<'BLADE'
@blaze
<div>User: {{ $sharedUser->name }}, Debug: {{ $sharedConfig['debug'] ? 'yes' : 'no' }}</div>
BLADE;

    $output = blade(
        view: '<x-test-complex />',
        components: [
            'test-complex' => $view,
        ]
    );

    expect(trim($output))->toBe('<div>User: John, Debug: yes</div>');
});
