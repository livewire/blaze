<?php

use Livewire\Blaze\Runtime\BlazeRuntime;

it('processPassthroughContent', function ($input, $results) {
    $input = str_replace('[UNBLAZE]', '[STARTCOMPILEDUNBLAZE:XXX][ENDCOMPILEDUNBLAZE:XXX]', $input);

    $results = array_combine(['ltrim', 'rtrim', 'trim'], $results);

    foreach ($results as $method => $result) {
        if (is_null($result)) {
            expect(app(BlazeRuntime::class)->processPassthroughContent($method, $input))->not->toContain('XXX:');
        } else {
            expect(app(BlazeRuntime::class)->processPassthroughContent($method, $input))->toContain('XXX:'.$result);
        }
    }
})
->with([
    ['[UNBLAZE]', ['ltrim', 'rtrim', 'trim']],
    ['[UNBLAZE]</div>', ['ltrim', null, 'ltrim']],
    ['<div>[UNBLAZE]', [null, 'rtrim', 'rtrim']],
    ['<div>[UNBLAZE]</div>', [null, null, null]],
]);