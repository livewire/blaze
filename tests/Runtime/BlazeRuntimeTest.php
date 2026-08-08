<?php

use Livewire\Blaze\Runtime\BlazeRuntime;
use Livewire\Blaze\Support\ComponentRepository;

it('resolve returns hash and requires blaze function', function () {
    $source = app(ComponentRepository::class)->get('input');

    expect(app(BlazeRuntime::class)->resolve('input'))->toBe($source->hash);

    expect(function_exists('_' . $source->hash))->toBeTrue();
});

it('resolve returns false when component doesnt exist', function () {
    expect(app(BlazeRuntime::class)->resolve('nonexistent'))->toBeFalse();
});

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
