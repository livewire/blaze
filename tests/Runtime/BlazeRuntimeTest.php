<?php

use Illuminate\Support\Facades\File;
use Illuminate\View\Compilers\BladeCompiler;
use Livewire\Blaze\Runtime\BlazeRuntime;

test('compiles stale cache when source and compiled timestamps are equal', function () {
    $dir = sys_get_temp_dir().'/blaze-'.uniqid();
    $path = $dir.'/button.blade.php';

    try {
        File::ensureDirectoryExists($dir);
        File::put($path, 'fresh');

        $compiled = app(BladeCompiler::class)->getCompiledPath($path);
        $timestamp = now()->addSecond()->timestamp;

        File::put($compiled, 'stale');

        touch($path, $timestamp);
        touch($compiled, $timestamp);
        clearstatcache(true);

        expect(File::lastModified($path))->toBe(File::lastModified($compiled));

        app(BlazeRuntime::class)->compile($path, $compiled);

        expect(File::get($compiled))->toContain('fresh');
    } finally {
        File::delete($compiled ?? '');
        File::deleteDirectory($dir);
    }
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
