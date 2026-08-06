<?php

use Livewire\Blaze\BladeRenderer;
use Livewire\Blaze\BladeService;
use Livewire\Blaze\Folder\Foldable;
use Livewire\Blaze\Parser\Parser;
use Livewire\Blaze\Support\ComponentRepository;

test('compiles unblaze blocks', function () {
    $input = '<x-foldable.input-unblaze name="address" />';

    $node = app(Parser::class)->parse($input)[0];
    $foldable = new Foldable($node, app(ComponentRepository::class)->get('foldable.input-unblaze'), app(BladeRenderer::class), app(BladeService::class));

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
    $foldable = new Foldable($node, app(ComponentRepository::class)->get('foldable.nested-input-unblaze'), app(BladeRenderer::class), app(BladeService::class));

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
    $foldable = new Foldable($node, app(ComponentRepository::class)->get('foldable.input-unblaze'), app(BladeRenderer::class), app(BladeService::class));

    expect($foldable->fold())->toEqualCollapsingWhitespace(
        sprintf('<input %s >', join('', [
            '<?php if (isset($scope)) $__scope = $scope; ?>',
            '<?php $scope = array ( \'name\' => $field, ); ?>',
            ' {{ $errors->has($scope[\'name\']) }} ',
            '<?php if (isset($__scope)) { $scope = $__scope; unset($__scope); } ?>'
        ]))
    );
});
