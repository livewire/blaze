<?php

use Illuminate\Support\Facades\File;
use Illuminate\View\Compilers\BladeCompiler;
use Livewire\Blaze\BladeRenderer;
use Livewire\Blaze\BlazeManager;

use function Livewire\invade;

beforeEach(fn () => app(BladeRenderer::class)->deleteTemporaryCacheDirectory());

test('compiles component source into the temporary cache', function () {
    $path = fixture_path('views/components/foldable/input.blade.php');

    app(BladeRenderer::class)->render($path);

    expect(File::exists(config('view.compiled').'/blaze/'.basename(app(BladeCompiler::class)->getCompiledPath($path))))->toBeTrue();
});

test('makes attributes available to aware props', function () {
    $output = app(BladeRenderer::class)->render(
        fixture_path('views/components/foldable/input-aware.blade.php'),
        data: ['type' => 'number'],
        attributes: ['type' => 'number'],
    );

    expect($output)->toEqualCollapsingWhitespace('<input type="number" >');
});

test('makes slots available to aware props', function () {
    $output = app(BladeRenderer::class)->render(
        fixture_path('views/components/foldable/input-aware.blade.php'),
        slots: ['type' => 'number'],
    );

    expect($output)->toEqualCollapsingWhitespace('<input type="number" >');
});

test('makes parents attributes available to aware props', function () {
    $output = app(BladeRenderer::class)->render(
        fixture_path('views/components/foldable/input-aware.blade.php'),
        awareData: ['type' => 'number'],
    );

    expect($output)->toEqualCollapsingWhitespace('<input type="number" >');
});

test('processes unblaze blocks', function () {
    $output = app(BladeRenderer::class)->render(
        fixture_path('views/components/foldable/input-unblaze.blade.php'),
        data: ['name' => 'address'],
        attributes: ['name' => 'address'],
    );

    expect($output)->toEqualCollapsingWhitespace(sprintf('<input %s >', join('', [
        '<?php if (isset($scope)) $__scope = $scope; ?>',
        '<?php $scope = array ( \'name\' => \'address\', ); ?>',
        ' {{ $errors->has($scope[\'name\']) }} ',
        '<?php if (isset($__scope)) { $scope = $__scope; unset($__scope); } ?>',
    ])));
});

test('recompiles stale cache of folded components', function () {
    $dir = sys_get_temp_dir().'/blaze-'.uniqid();
    $path = $dir.'/button.blade.php';

    try {
        File::ensureDirectoryExists($dir);
        File::put($path, '@blaze(fold: true) stale');

        $blaze = app(BlazeManager::class);
        $renderer = app(BladeRenderer::class);
        $blade = app(BladeCompiler::class);
        $bladeCachePath = invade($blade)->cachePath;

        // We can't use the renderer here because it would `require` the file
        // and register a global function, which would produce stale output
        // even after recompiling. So instead we'll simulate folding and
        // we will only compile the file in the blaze cache directory
        invade($blade)->cachePath = $renderer->getTemporaryCachePath();

        $blaze->startFolding();
        $blade->compile($path);
        $blaze->stopFolding();

        invade($blade)->cachePath = $bladeCachePath;

        // Now change the source and ensure the renderer output is fresh
        File::put($path, '@blaze(fold: true) fresh');

        touch($path, time() + 2);

        expect($renderer->render($path))->toContain('fresh');
    } finally {
        File::deleteDirectory($dir);
    }
});

test('recompiles stale cache when source and compiled timestamps are equal', function () {
    $dir = sys_get_temp_dir().'/blaze-'.uniqid();
    $path = $dir.'/button.blade.php';

    try {
        File::ensureDirectoryExists($dir);
        File::put($path, '@blaze(fold: true) stale');

        $blaze = app(BlazeManager::class);
        $renderer = app(BladeRenderer::class);
        $blade = app(BladeCompiler::class);
        $bladeCachePath = invade($blade)->cachePath;

        // We can't use the renderer here because it would `require` the file
        // and register a global function, which would produce stale output
        // even after recompiling. So instead we'll simulate folding and
        // we will only compile the file in the blaze cache directory
        invade($blade)->cachePath = $renderer->getTemporaryCachePath();

        $blaze->startFolding();
        $blade->compile($path);
        $blaze->stopFolding();

        $compiled = $blade->getCompiledPath($path);

        invade($blade)->cachePath = $bladeCachePath;

        // Now change the source and give it the same timestamp as the compiled file
        $timestamp = now()->addSecond()->timestamp;

        File::put($path, '@blaze(fold: true) fresh');

        touch($path, $timestamp);
        touch($compiled, $timestamp);
        clearstatcache(true);

        expect(File::lastModified($path))->toBe(File::lastModified($compiled));

        expect($renderer->render($path))->toContain('fresh');
    } finally {
        File::deleteDirectory($dir);
    }
});
