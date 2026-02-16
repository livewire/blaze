<?php

use Livewire\Blaze\Folder\Foldable;
use Livewire\Blaze\Parser\Attribute;
use Livewire\Blaze\Parser\Parser;
use Livewire\Blaze\Support\ComponentSource;

test('folds dynamic attributes', function () {
    $input = '<x-foldable.input :type="$type" />';

    $node = app(Parser::class)->parse($input)[0];
    $foldable = new Foldable($node, new ComponentSource($node->name));

    expect($foldable->fold())->toEqualCollapsingWhitespace(
        '<input type="{{ $type }}" >'
    );
});

test('folds slots', function () {
    $input = <<<'BLADE'
        <x-foldable.card>
            <x-slot:header>
                {{ $title }}
            </x-slot:header>
            {{ $content }}
            <x-slot:footer>
                {{ $author }}
            </x-slot:footer>
        </x-card>
        BLADE
    ;

    $node = app(Parser::class)->parse($input)[0];
    $foldable = new Foldable($node, new ComponentSource($node->name));

    expect($foldable->fold())->toEqualCollapsingWhitespace(
        '<div>{{ $title }} | {{ $content }} | {{ $author }}</div>'
    );
});

test('folds loose content', function () {
    $input = <<<'BLADE'
        <x-foldable.card>
            Before
            <x-slot:header>
                {{ $title }}
            </x-slot:header>
            {{ $content }}
            <x-slot:footer>
                {{ $author }}
            </x-slot:footer>
            After
        </x-card>
        BLADE
    ;

    $node = app(Parser::class)->parse($input)[0];
    $foldable = new Foldable($node, new ComponentSource($node->name));

    expect($foldable->fold())->toEqualCollapsingWhitespace(
        '<div>{{ $title }} | Before {{ $content }} After | {{ $author }}</div>'
    );
});

test('preserves dynamic attributes with static false', function () {
    $input = '<x-foldable.input :disabled="false" />';

    $node = app(Parser::class)->parse($input)[0];
    $foldable = new Foldable($node, new ComponentSource($node->name));

    expect($foldable->fold())->toEqualCollapsingWhitespace(
        '<input type="text" >'
    );
});

test('preserves dynamic attributes with static null', function () {
    $input = '<x-foldable.input :disabled="null" />';

    $node = app(Parser::class)->parse($input)[0];
    $foldable = new Foldable($node, new ComponentSource($node->name));

    expect($foldable->fold())->toEqualCollapsingWhitespace(
        '<input type="text" >'
    );
});

test('merges aware props from parent attributes', function () {
    $input = '<x-foldable.input-aware />';

    $node = app(Parser::class)->parse($input)[0];
    $foldable = new Foldable($node, new ComponentSource($node->name));

    $node->setParentsAttributes([
        'type' => new Attribute(
            name: 'type',
            value: 'number',
            propName: 'type',
            dynamic: false
        ),
    ]);

    expect($foldable->fold())->toEqualCollapsingWhitespace(
        '<input type="number" >'
    );
});

test('merges dynamic aware props from parent attributes', function () {
    $input = '<x-foldable.input-aware />';

    $node = app(Parser::class)->parse($input)[0];
    $node->setParentsAttributes([
        'type' => new Attribute(
            name: 'type',
            value: '$type',
            propName: 'type',
            dynamic: true,
            prefix: ':',
            quotes: '"',
        ),
    ]);

    $foldable = new Foldable($node, new ComponentSource($node->name));

    expect($foldable->fold())->toEqualCollapsingWhitespace(
        '<input type="{{ $type }}" >'
    );
});

test('folds dynamic attributes passed through attribute bag', function () {
    $input = '<x-foldable.input :readonly="$readonly" />';

    $node = app(Parser::class)->parse($input)[0];
    $foldable = new Foldable($node, new ComponentSource($node->name));

    expect($foldable->fold())->toEqualCollapsingWhitespace(
        sprintf('<input %s type="text" >', join('', [
            '<?php if (($__blazeAttr = $readonly) !== false && !is_null($__blazeAttr)): ?>',
            'readonly="<?php echo e($__blazeAttr === true ? \'readonly\' : $__blazeAttr); ?>"',
            '<?php endif; unset($__blazeAttr); ?>',
        ]))
    );
});

test('folds dynamic attributes used inside unblaze directive', function () {
    $input = '<x-foldable.input-unblaze :name="$field" />';

    $node = app(Parser::class)->parse($input)[0];
    $foldable = new Foldable($node, new ComponentSource($node->name));

    expect($foldable->fold())->toEqualCollapsingWhitespace(
        sprintf('<input %s >', join('', [
            '<?php if (isset($scope)) $__scope = $scope; ?>',
            '<?php $scope = array ( \'name\' => $field, ); ?>',
            ' {{ $errors->has($scope[\'name\']) }} ',
            '<?php if (isset($__scope)) { $scope = $__scope; unset($__scope); } ?>'
        ]))
    );
});

test('wraps output with aware macros if descendants use aware', function () {
    $input = '<x-foldable.card name="John"><x-aware-descendant /></x-foldable.card>';

    $node = app(Parser::class)->parse($input)[0];
    $node->hasAwareDescendants = true;

    $foldable = new Foldable($node, new ComponentSource($node->name));

    expect($foldable->fold())->toEqualCollapsingWhitespace(join('', [
        '<?php $__blaze->pushData([\'name\' => \'John\']); $__env->pushConsumableComponentData([\'name\' => \'John\']); ?>',
        '<div>Default | <x-aware-descendant /> | Default</div>',
        '<?php $__blaze->popData(); $__env->popConsumableComponentData(); ?>',
    ]));
});

test('compiles dynamic attributes in aware macros', function () {
    $input = '<x-foldable.card :name="$name"><x-aware-descendant /></x-foldable.card>';

    $node = app(Parser::class)->parse($input)[0];
    $node->hasAwareDescendants = true;

    $foldable = new Foldable($node, new ComponentSource($node->name));

    expect($foldable->fold())->toEqualCollapsingWhitespace(join('', [
        '<?php $__blaze->pushData([\'name\' => $name]); $__env->pushConsumableComponentData([\'name\' => $name]); ?>',
        '<div>Default | <x-aware-descendant /> | Default</div>',
        '<?php $__blaze->popData(); $__env->popConsumableComponentData(); ?>',
    ]));
});

test('compiles echo attributes in aware macros', function () {
    $input = '<x-foldable.card name="Mr. {{ $name }}"><x-aware-descendant /></x-foldable.card>';

    $node = app(Parser::class)->parse($input)[0];
    $node->hasAwareDescendants = true;

    $foldable = new Foldable($node, new ComponentSource($node->name));

    expect($foldable->fold())->toEqualCollapsingWhitespace(join('', [
        '<?php $__blaze->pushData([\'name\' => \'Mr. \'.e($name)]); $__env->pushConsumableComponentData([\'name\' => \'Mr. \'.e($name)]); ?>',
        '<div>Default | <x-aware-descendant /> | Default</div>',
        '<?php $__blaze->popData(); $__env->popConsumableComponentData(); ?>',
    ]));
});