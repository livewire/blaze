<?php

use Illuminate\Support\Facades\File;
use Illuminate\View\Compilers\BladeCompiler;
use Livewire\Blaze\BladeRenderer;
use Livewire\Blaze\BlazeManager;
use Livewire\Blaze\Parser\Parser;
use Livewire\Blaze\Support\AttributeParser;
use Livewire\Blaze\Support\Utils;
use Livewire\Blaze\Unblaze;

use function Livewire\invade;

beforeEach(fn () => app(BladeRenderer::class)->deleteTemporaryCacheDirectory());

test('compiles component source into the temporary cache', function () {
    $path = fixture_path('views/components/foldable/input.blade.php');
    $node = app(Parser::class)->parse('<x-foldable.input />')[0];

    app(BladeRenderer::class)->render($node, $path);

    expect(File::exists(config('view.compiled').'/blaze/'.Utils::hash($path).'.php'))->toBeTrue();
});

test('makes attributes available to aware props', function () {
    $node = app(Parser::class)->parse('<x-foldable.input-aware type="number" />')[0];

    $output = app(BladeRenderer::class)->render($node, fixture_path('views/components/foldable/input-aware.blade.php'));

    expect($output)->toEqualCollapsingWhitespace('<input type="number" >');
});

test('makes slots available to aware props', function () {
    $node = app(Parser::class)->parse('<x-foldable.input-aware><x-slot:type>number</x-slot:type></x-foldable.input-aware>')[0];

    $output = app(BladeRenderer::class)->render($node, fixture_path('views/components/foldable/input-aware.blade.php'));

    expect($output)->toEqualCollapsingWhitespace('<input type="number" >');
});

test('makes parents attributes available to aware props', function () {
    $node = app(Parser::class)->parse('<x-foldable.input-aware />')[0];

    $node->setParentsAttributes(
        app(AttributeParser::class)->parse('type="number"')
    );

    $output = app(BladeRenderer::class)->render($node, fixture_path('views/components/foldable/input-aware.blade.php'));

    expect($output)->toEqualCollapsingWhitespace('<input type="number" >');
});

test('processes unblaze blocks', function () {
    $node = app(Parser::class)->parse('<x-foldable.input-unblaze name="address" />')[0];

    $output = app(BladeRenderer::class)->render($node, fixture_path('views/components/foldable/input-unblaze.blade.php'));

    expect($output)->toEqualCollapsingWhitespace(sprintf('<input %s >', join('', [
        '<?php if (isset($scope)) $__scope = $scope; ?>',
        '<?php $scope = array ( \'name\' => \'address\', ); ?>',
        ' {{ $errors->has($scope[\'name\']) }} ',
        '<?php if (isset($__scope)) { $scope = $__scope; unset($__scope); } ?>',
    ])));
});

test('processes cached unblaze blocks after request state is flushed', function () {
    $path = fixture_path('views/components/foldable/input-unblaze.blade.php');
    $node = app(Parser::class)->parse('<x-foldable.input-unblaze name="address" />')[0];
    $renderer = app(BladeRenderer::class);

    $firstRender = $renderer->render($node, $path);

    Unblaze::flushState();

    expect($renderer->render($node, $path))->toBe($firstRender);
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

        $node = app(Parser::class)->parse('<x-button />')[0];

        expect($renderer->render($node, $path))->toContain('fresh');
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

        invade($blade)->cachePath = $bladeCachePath;

        // Now change the source and give it the same timestamp as the compiled file
        $compiled = $renderer->getTemporaryCachePath().'/'.Utils::hash($path).'.php';
        $timestamp = now()->addSecond()->timestamp;

        File::put($path, '@blaze(fold: true) fresh');

        touch($path, $timestamp);
        touch($compiled, $timestamp);
        clearstatcache(true);

        expect(File::lastModified($path))->toBe(File::lastModified($compiled));

        $node = app(Parser::class)->parse('<x-button />')[0];

        expect($renderer->render($node, $path))->toContain('fresh');
    } finally {
        File::deleteDirectory($dir);
    }
});
