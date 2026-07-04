<?php

use Livewire\Blaze\Support\Utils;

test('exceptBlazeVariables removes blaze internals and keeps user variables', function () {
    $hash = Utils::hash('components/layout.blade.php');

    $variables = [
        'message' => 'Hello',
        'count' => 1,
        '__blaze' => new stdClass,
        '__blazeViewName' => 'layout',
        '__attrs'.$hash => ['title' => 'Test'],
        '__slots'.$hash => [],
        '__attrsStack'.$hash => [],
        '__slotsStack'.$hash => [],
    ];

    expect(Utils::exceptBlazeVariables($variables))->toBe([
        'message' => 'Hello',
        'count' => 1,
    ]);
});
