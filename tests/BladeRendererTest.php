<?php

use Illuminate\Support\Facades\File;
use Livewire\Blaze\BladeRenderer;
use Livewire\Blaze\Parser\Parser;
use Livewire\Blaze\Support\AttributeParser;
use Livewire\Blaze\Support\Utils;

afterEach(fn () => app(BladeRenderer::class)->deleteTemporaryCacheDirectory());

test('compiles component source into the temporary cache', function () {
    $path = fixture_path('views/components/foldable/input.blade.php');
    $node = app(Parser::class)->parse('<x-foldable.input />')->nodes[0];

    app(BladeRenderer::class)->render($node, $path);

    expect(File::exists(config('view.compiled').'/blaze/'.Utils::hash($path).'.php'))->toBeTrue();
});

test('makes attributes available to aware props', function () {
    $node = app(Parser::class)->parse('<x-foldable.input-aware type="number" />')->nodes[0];

    $output = app(BladeRenderer::class)->render($node, fixture_path('views/components/foldable/input-aware.blade.php'));

    expect($output)->toEqualCollapsingWhitespace('<input type="number" >');
});

test('makes slots available to aware props', function () {
    $node = app(Parser::class)->parse('<x-foldable.input-aware><x-slot:type>number</x-slot:type></x-foldable.input-aware>')->nodes[0];

    $output = app(BladeRenderer::class)->render($node, fixture_path('views/components/foldable/input-aware.blade.php'));

    expect($output)->toEqualCollapsingWhitespace('<input type="number" >');
});

test('makes parents attributes available to aware props', function () {
    $node = app(Parser::class)->parse('<x-foldable.input-aware />')->nodes[0];

    $node->setParentsAttributes(
        app(AttributeParser::class)->parse('type="number"')
    );

    $output = app(BladeRenderer::class)->render($node, fixture_path('views/components/foldable/input-aware.blade.php'));

    expect($output)->toEqualCollapsingWhitespace('<input type="number" >');
});

test('processes unblaze blocks', function () {
    $node = app(Parser::class)->parse('<x-foldable.input-unblaze name="address" />')->nodes[0];

    $output = app(BladeRenderer::class)->render($node, fixture_path('views/components/foldable/input-unblaze.blade.php'));

    expect($output)->toEqualCollapsingWhitespace(sprintf('<input %s >', join('', [
        '<?php if (isset($scope)) $__scope = $scope; ?>',
        '<?php $scope = array ( \'name\' => \'address\', ); ?>',
        ' {{ $errors->has($scope[\'name\']) }} ',
        '<?php if (isset($__scope)) { $scope = $__scope; unset($__scope); } ?>',
    ])));
});

test('deletes the temporary cache directory', function () {
    $node = app(Parser::class)->parse('<x-foldable.input />')->nodes[0];

    app(BladeRenderer::class)->render($node, fixture_path('views/components/foldable/input.blade.php'));

    expect(File::isDirectory(config('view.compiled').'/blaze'))->toBeTrue();

    app(BladeRenderer::class)->deleteTemporaryCacheDirectory();

    expect(File::isDirectory(config('view.compiled').'/blaze'))->toBeFalse();
});
