<?php

use Livewire\Blaze\Support\Directives;

test('ignores directives in php blocks', function ($input) {
    expect((new Directives($input))->has('aware'))->toBeFalse();
})->with([
    ['@php // @aware @endphp'],
    ['<?php // @aware ?>'],
]);