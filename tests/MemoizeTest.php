<?php

use Livewire\Blaze\Memoizer\Memo;

beforeEach(function () {
    Memo::clear();
});

test('key produces stable cache keys for same inputs', function () {
    $a = Memo::key('avatar', ['circle' => true, 'src' => '/img.jpg']);
    $b = Memo::key('avatar', ['circle' => true, 'src' => '/img.jpg']);

    expect($a)->toBeString()->toBe($b);
});

test('key returns null for non-serializable params', function ($params) {
    expect(Memo::key('avatar', $params))->toBeNull();
})->with([
    'NAN'  => [['value' => NAN]],
    'INF'  => [['value' => INF]],
]);

test('key differs for different params', function () {
    $a = Memo::key('avatar', ['circle' => true]);
    $b = Memo::key('avatar', ['circle' => false]);

    expect($a)->not->toBe($b);
});

test('store and retrieve memoized values', function () {
    $key = Memo::key('component', ['a' => 1]);

    expect(Memo::has($key))->toBeFalse();

    Memo::put($key, '<div>cached</div>');

    expect(Memo::has($key))->toBeTrue();
    expect(Memo::get($key))->toBe('<div>cached</div>');
});

test('clear removes all entries', function () {
    Memo::put('key1', 'val1');
    Memo::put('key2', 'val2');

    Memo::clear();

    expect(Memo::has('key1'))->toBeFalse();
    expect(Memo::has('key2'))->toBeFalse();
});

test('memoized component returns identical output across renders', function () {
    $template = '<x-memoize />';

    $a = blade($template);
    $b = blade($template);

    expect($a)->toContain('<div>');
    expect($a)->toBe($b);
});
