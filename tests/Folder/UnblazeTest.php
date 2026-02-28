<?php

use Livewire\Blaze\BladeService;
use Livewire\Blaze\Folder\Foldable;
use Livewire\Blaze\Support\ComponentSource;
use Livewire\Blaze\Parser\Parser;

test('compiles unblaze blocks', function () {
    $input = '<x-foldable.input-unblaze name="address" />';

    $node = app(Parser::class)->parse($input)[0];
    $foldable = new Foldable($node, new ComponentSource(fixture_path('components/foldable/input-unblaze.blade.php')), app(BladeService::class));

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
    $foldable = new Foldable($node, new ComponentSource(fixture_path('components/foldable/nested-input-unblaze.blade.php')), app(BladeService::class));

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
    $foldable = new Foldable($node, new ComponentSource(fixture_path('components/foldable/input-unblaze.blade.php')), app(BladeService::class));

    expect($foldable->fold())->toEqualCollapsingWhitespace(
        sprintf('<input %s >', join('', [
            '<?php if (isset($scope)) $__scope = $scope; ?>',
            '<?php $scope = array ( \'name\' => $field, ); ?>',
            ' {{ $errors->has($scope[\'name\']) }} ',
            '<?php if (isset($__scope)) { $scope = $__scope; unset($__scope); } ?>'
        ]))
    );
});
