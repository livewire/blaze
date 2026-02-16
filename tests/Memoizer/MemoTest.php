<?php

use Livewire\Blaze\Memoizer\Memo;

test('key produces stable cache keys for same inputs', function () {
    $actual = Memo::key('avatar', ['circle' => true, 'src' => '/img.jpg']);
    $expected = Memo::key('avatar', ['src' => '/img.jpg', 'circle' => true]);

    expect($actual)->toBeString()->toBe($expected);
});

test('key returns null for non-serializable params', function () {
    expect(Memo::key('avatar', ['callback' => fn () => null]))->toBeNull();
});