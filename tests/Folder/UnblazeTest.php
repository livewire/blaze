<?php

use Illuminate\Support\Facades\Artisan;
use Livewire\Blaze\BladeRenderer;
use Livewire\Blaze\BladeService;
use Livewire\Blaze\Folder\Foldable;
use Livewire\Blaze\Parser\Parser;
use Livewire\Blaze\Unblaze;

beforeEach(fn () => Artisan::call('view:clear'));

test('compiles unblaze blocks', function () {
    $input = '<x-foldable.input-unblaze name="address" />';

    $node = app(Parser::class)->parse($input)[0];
    $foldable = new Foldable($node, fixture_path('views/components/foldable/input-unblaze.blade.php'), app(BladeRenderer::class), app(BladeService::class));

    expect($foldable->fold())->toEqualCollapsingWhitespace(
        sprintf('<input %s >', join('', [
            '<?php if (isset($scope)) $__scope = $scope; ?>',
            '<?php $scope = array ( \'name\' => \'address\', ); ?>',
            ' {{ $errors->has($scope[\'name\']) }} ',
            '<?php if (isset($__scope)) { $scope = $__scope; unset($__scope); } ?>'
        ]))
    );
});

test('compiles nested unblaze blocks', function () {
    $input = '<x-foldable.nested-input-unblaze />';

    $node = app(Parser::class)->parse($input)[0];
    $foldable = new Foldable($node, fixture_path('views/components/foldable/nested-input-unblaze.blade.php'), app(BladeRenderer::class), app(BladeService::class));

    expect($foldable->fold())->toEqualCollapsingWhitespace(
        sprintf('<div> <input %s ></div>', join('', [
            '<?php if (isset($scope)) $__scope = $scope; ?>',
            '<?php $scope = array ( \'name\' => \'address\', ); ?>',
            ' {{ $errors->has($scope[\'name\']) }} ',
            '<?php if (isset($__scope)) { $scope = $__scope; unset($__scope); } ?>'
        ]))
    );
});

test('folds dynamic attributes used inside unblaze directive', function () {
    $input = '<x-foldable.input-unblaze :name="$field" />';

    $node = app(Parser::class)->parse($input)[0];
    $foldable = new Foldable($node, fixture_path('views/components/foldable/input-unblaze.blade.php'), app(BladeRenderer::class), app(BladeService::class));

    expect($foldable->fold())->toEqualCollapsingWhitespace(
        sprintf('<input %s >', join('', [
            '<?php if (isset($scope)) $__scope = $scope; ?>',
            '<?php $scope = array ( \'name\' => $field, ); ?>',
            ' {{ $errors->has($scope[\'name\']) }} ',
            '<?php if (isset($__scope)) { $scope = $__scope; unset($__scope); } ?>'
        ]))
    );
});

test('processes replacements in compiled files', function () {
    $path = fixture_path('views/components/foldable/input-unblaze.blade.php');
    $data = ['name' => 'address'];

    // Force compilation and store replacements...
    $before = app(BladeRenderer::class)->render($path, $data, $data);

    // Flush replacements to simulate a separate process...
    Unblaze::flushState();

    // Ensure we can restore replacements from the compiled file...
    $after = app(BladeRenderer::class)->render($path, $data, $data);

    expect($before)->toBe($after);
});

test('escapes props bound to child attributes', function () {
    $input = '<x-foldable.forwarded-prop-unblaze :value="$value" />';

    $node = app(Parser::class)->parse($input)[0];
    $foldable = new Foldable($node, fixture_path('views/components/foldable/forwarded-prop-unblaze.blade.php'), app(BladeRenderer::class), app(BladeService::class));

    expect($foldable->fold())->toEqualCollapsingWhitespace(
        sprintf('<div %s ></div>', join('', [
            '<?php if (isset($scope)) $__scope = $scope; ?>',
            '<?php $scope = array ( \'attributes\' => array ( \'aria-label\' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($value), ), ); ?>',
            ' {{ new \Illuminate\View\ComponentAttributeBag($scope[\'attributes\']) }} ',
            '<?php if (isset($__scope)) { $scope = $__scope; unset($__scope); } ?>'
        ]))
    );
});

test('keeps props bound to child attributes as trusted html unescaped', function () {
    $input = '<x-foldable.forwarded-html-prop-unblaze :value="$value" />';

    $node = app(Parser::class)->parse($input)[0];
    $foldable = new Foldable($node, fixture_path('views/components/foldable/forwarded-html-prop-unblaze.blade.php'), app(BladeRenderer::class), app(BladeService::class));

    expect($foldable->fold())->toEqualCollapsingWhitespace(
        sprintf('<div %s ></div>', join('', [
            '<?php if (isset($scope)) $__scope = $scope; ?>',
            '<?php $scope = array ( \'attributes\' => array ( \'aria-label\' => $value, ), ); ?>',
            ' {{ new \Illuminate\View\ComponentAttributeBag($scope[\'attributes\']) }} ',
            '<?php if (isset($__scope)) { $scope = $__scope; unset($__scope); } ?>'
        ]))
    );
});
