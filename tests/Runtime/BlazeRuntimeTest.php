<?php

use Illuminate\Support\Facades\File;
use Illuminate\View\Compilers\BladeCompiler;
use Livewire\Blaze\Runtime\BlazeAttributeBag;
use Livewire\Blaze\Runtime\BlazeRuntime;

use function Livewire\invade;

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

        app(BlazeRuntime::class)->compile($path);

        expect(File::get($compiled))->toContain('fresh');
    } finally {
        File::delete($compiled ?? '');
        File::deleteDirectory($dir);
    }
});

test('delegated attributes render exactly as the parent attributes', function () {
    $attributes = [
        'aria-label' => e('x" onmouseover="alert(1)'),
        'data-label' => 'A &copy; B',
        'foo-bar' => 'A &amp; B',
        'foo_bar' => 'C',
        'disabled' => true,
    ];

    [$data, $bound, $keys] = app(BlazeRuntime::class)->delegatedAttributes($attributes);

    expect(BlazeAttributeBag::make($data, $bound, $keys)->getAttributes())->toBe($attributes);
    expect($data['fooBar'])->toBe('A & B');
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

// Since https://github.com/laravel/framework/pull/61822, compiled paths get a suffix when a custom echo format is used.
test('compiles to the blade compiler path with a custom echo format', function () {
    $path = fixture_path('views/components/input.blade.php');
    $compiler = app(BladeCompiler::class);

    $compiler->withoutDoubleEncoding();

    expect(app(BlazeRuntime::class)->compile($path))->toBe($compiler->getCompiledPath($path))->toBeFile();
});

test('compiles to the blade compiler path with a relative hash', function () {
    $path = fixture_path('views/components/input.blade.php');
    $compiler = app(BladeCompiler::class);

    invade($compiler)->basePath = dirname(__DIR__, 2);

    expect(app(BlazeRuntime::class)->compile($path))->toBe($compiler->getCompiledPath($path))->toBeFile();
});

test('compiles to the blade compiler path with a custom compiled extension', function () {
    $path = fixture_path('views/components/input.blade.php');
    $compiler = app(BladeCompiler::class);

    invade($compiler)->compiledExtension = 'phtml';

    expect(app(BlazeRuntime::class)->compile($path))->toBe($compiler->getCompiledPath($path))->toBeFile();
});
