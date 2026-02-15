<?php

/*
|--------------------------------------------------------------------------
| Sanitization & XSS Prevention
|--------------------------------------------------------------------------
| Verifies that values are escaped exactly once — never double-escaped,
| never left raw. This is a critical safety boundary.
*/

test('props with special characters are escaped once not double-escaped', function ($input, $mustContain, $mustNotContain) {
    $result = blade(
        view: '<x-label :label="$text" />',
        components: ['label' => '@blaze
@props(["label" => "Default"])
<span>{{ $label }}</span>'],
        data: ['text' => $input],
    );

    expect($result)->toContain($mustContain);
    expect($result)->not->toContain($mustNotContain);
})->with([
    'ampersand'  => ['Save & Continue', 'Save &amp; Continue', '&amp;amp;'],
    'xss script' => ['<script>alert("xss")</script>', '&lt;script&gt;', '&amp;lt;'],
]);

test('dynamic attributes with dangerous values are sanitized', function () {
    $result = blade(
        view: '<x-btn :data-value="$input" />',
        components: ['btn' => '@blaze
<button {{ $attributes }}>Click</button>'],
        data: ['input' => '<script>alert("xss")</script>'],
    );

    expect($result)
        ->toContain('&lt;script&gt;')
        ->toContain('&lt;/script&gt;');
});

test('dynamic attributes with ampersand are escaped', function () {
    $result = blade(
        view: '<x-btn :data-value="$text" />',
        components: ['btn' => '@blaze
<button {{ $attributes }}>Click</button>'],
        data: ['text' => 'Tom & Jerry'],
    );

    expect($result)->toContain('data-value="Tom &amp; Jerry"');
});

test('static attributes preserve original format', function () {
    $result = blade(
        view: '<x-btn data-value="safe-value" />',
        components: ['btn' => '@blaze
<button {{ $attributes }}>Click</button>'],
    );

    expect($result)->toContain('data-value="safe-value"');
});

test('slot attributes with dangerous values are sanitized', function () {
    $result = blade(
        view: '<x-panel><x-slot:header :data-test="$input">Header</x-slot:header>Body</x-panel>',
        components: ['panel' => '@blaze
<div class="card">
    <div {{ $header->attributes }}>{{ $header }}</div>
    <div class="card-body">{{ $slot }}</div>
</div>'],
        data: ['input' => '<script>alert("xss")</script>'],
    );

    expect($result)->toContain('&lt;script&gt;');
});

test('aware values are escaped once when rendered', function () {
    $result = \Illuminate\Support\Facades\Blade::render(
        '<x-aware-menu :color="$color"><x-aware-menu-item>Item</x-aware-menu-item></x-aware-menu>',
        ['color' => 'Tom & Jerry'],
    );

    expect($result)
        ->toContain('text-Tom &amp; Jerry-800')
        ->not->toContain('&amp;amp;');
});

test('stringable objects are not double-escaped', function () {
    $result = blade(
        view: '<x-label :label="str(\'<b>Bold</b>\')" />',
        components: ['label' => '@blaze
@props(["label"])
<span>{{ $label }}</span>'],
    );

    expect($result)
        ->toContain('&lt;b&gt;Bold&lt;/b&gt;')
        ->not->toContain('&amp;lt;');
});
