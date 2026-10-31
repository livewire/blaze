<?php

use Livewire\Blaze\BladeRenderer;
use Livewire\Blaze\BladeService;
use Livewire\Blaze\Exceptions\LeftoverPlaceholdersException;
use Livewire\Blaze\Folder\Foldable;
use Livewire\Blaze\Parser\Nodes\ComponentNode;
use Livewire\Blaze\Parser\Parser;
use Livewire\Blaze\Support\AttributeParser;

use function Pest\Laravel\mock;

test('replaces and restores bound attributes', function () {
    $node = app(Parser::class)->parse('<x-input :type="$type" />')[0];

    mock(BladeRenderer::class)
        ->expects('render')
        ->once()->withArgs(function (ComponentNode $node) {
            expect($node->render())->toBe('<x-input type="BLAZE_PLACEHOLDER_0_raw&_&amp;_" />');

            return true;
        })
        ->andReturn('<input type="BLAZE_PLACEHOLDER_0_raw&amp;_&amp;amp;_" >');

    $output = (new Foldable($node, '', app(BladeRenderer::class), app(BladeService::class)))->fold();

    expect($output)->toEqualCollapsingWhitespace('<input type="{{ $type }}" >');
});

test('preserves bound attributes with static constant values', function (string $value) {
    $node = app(Parser::class)->parse('<x-input :disabled="' . $value . '" />')[0];

    mock(BladeRenderer::class)
        ->expects('render')
        ->once()->withArgs(function (ComponentNode $node) use ($value) {
            expect($node->render())->toBe('<x-input :disabled="' . $value . '" />');

            return true;
        })
        ->andReturn('');

    (new Foldable($node, '', app(BladeRenderer::class), app(BladeService::class)))->fold();
})->with(['false', 'true', 'null']);

test('replaces parents attributes', function () {
    $node = app(Parser::class)->parse('<x-input />')[0];

    $node->setParentsAttributes(
        app(AttributeParser::class)->parse(':type="$type"')
    );

    mock(BladeRenderer::class)
        ->expects('render')
        ->once()->withArgs(function (ComponentNode $node) {
            expect($node->render())->toBe('<x-input />');
            expect($node->parentsAttributes['type']->render())->toBe('type="BLAZE_PLACEHOLDER_0_raw&_&amp;_"');

            return true;
        })
        ->andReturn('<input type="BLAZE_PLACEHOLDER_0_raw&amp;_&amp;amp;_">');

    $output = (new Foldable($node, '', app(BladeRenderer::class), app(BladeService::class)))->fold();

    expect($output)->toEqualCollapsingWhitespace('<input type="{{ $type }}">');
});

test('restores every occurrence of a dynamic attribute placeholder', function () {
    $node = app(Parser::class)->parse('<x-button wire:click="save({{ $id }})" />')[0];

    mock(BladeRenderer::class)
        ->expects('render')
        ->once()->withArgs(function (ComponentNode $node) {
            expect($node->render())->toBe('<x-button wire:click="BLAZE_PLACEHOLDER_0_" />');

            return true;
        })
        ->andReturn('<button wire:target="BLAZE_PLACEHOLDER_0_" wire:click="BLAZE_PLACEHOLDER_0_"></button>');

    $output = (new Foldable($node, '', app(BladeRenderer::class), app(BladeService::class)))->fold();

    expect($output)->toEqualCollapsingWhitespace(
        '<button wire:target="save({{ $id }})" wire:click="save({{ $id }})"></button>'
    );
});

test('restores bound attributes in html', function (string $rendered, string $expected) {
    $node = app(Parser::class)->parse('<x-input :type="$type" />')[0];

    mock(BladeRenderer::class)
        ->expects('render')
        ->once()
        ->andReturn($rendered);

    $output = (new Foldable($node, '', app(BladeRenderer::class), app(BladeService::class)))->fold();

    expect($output)->toBe($expected);
})->with([
    'raw copy' => ['<input type="BLAZE_PLACEHOLDER_0_raw&_&amp;_">', '<input type="{!! $__blaze->rawAttribute($type) !!}">'],
    'raw copy escaped once' => ['<input type="BLAZE_PLACEHOLDER_0_raw&amp;_&amp;amp;_">', '<input type="{{ $type }}">'],
    'attribute bag copy' => ['<input type="BLAZE_PLACEHOLDER_0_bag&_&amp;_">', '<input type="{!! \\Illuminate\\View\\Compilers\\BladeCompiler::sanitizeComponentAttribute($type) !!}">'],
    'next to other values' => ['<input class="p-4 BLAZE_PLACEHOLDER_0_bag&_&amp;_">', '<input class="p-4 {!! \\Illuminate\\View\\Compilers\\BladeCompiler::sanitizeComponentAttribute($type) !!}">'],
    'same copy twice' => ['<input type="BLAZE_PLACEHOLDER_0_raw&amp;_&amp;amp;_" title="BLAZE_PLACEHOLDER_0_raw&amp;_&amp;amp;_">', '<input type="{{ $type }}" title="{{ $type }}">'],
    'raw copy escaped once without double encoding' => ['<input type="BLAZE_PLACEHOLDER_0_raw&amp;_&amp;_">', '<input type="{{ $type }}">'],
    'attribute bag copy decoded and escaped' => ['<input type="BLAZE_PLACEHOLDER_0_bag&amp;_&amp;_">', '<input type="{!! e($__blaze->decodedAttribute($type)) !!}">'],
    'attribute bag copy next to the attribute bag' => ['<input [BLAZE_ATTR:BLAZE_PLACEHOLDER_0_bag&_&amp;_:type] title="BLAZE_PLACEHOLDER_0_raw&amp;_&amp;amp;_">', '<input <?php if (($__blazeAttr = $type) !== false && !is_null($__blazeAttr)): ?>type="<?php echo e($__blazeAttr === true ? \'type\' : $__blazeAttr); ?>"<?php endif; unset($__blazeAttr); ?> title="{{ $type }}">'],
]);

test('exports bound attributes in unblaze scopes', function (Closure $scope, string $expected) {
    $node = app(Parser::class)->parse('<x-input :type="$type" theme="dark-{{ $variant }}" />')[0];

    mock(BladeRenderer::class)
        ->expects('render')
        ->once()
        ->andReturnUsing(fn ($node, $path, $exportScope) => $exportScope($scope()));

    $output = (new Foldable($node, '', app(BladeRenderer::class), app(BladeService::class)))->fold();

    expect($output)->toBe($expected);
})->with([
    'raw copy' => [fn () => ['type' => 'BLAZE_PLACEHOLDER_0_raw&_&amp;_'], "['type' => \$__blaze->rawAttribute(\$type)]"],
    'attribute bag copy' => [fn () => ['type' => 'BLAZE_PLACEHOLDER_0_bag&_&amp;_'], "['type' => \\Illuminate\\View\\Compilers\\BladeCompiler::sanitizeComponentAttribute(\$type)]"],
    'echo attribute' => [fn () => ['theme' => 'BLAZE_PLACEHOLDER_1_'], "['theme' => 'dark-'.e(\$variant)]"],
    'nested' => [fn () => ['attributes' => ['type' => 'BLAZE_PLACEHOLDER_0_bag&_&amp;_', 'name' => 'email'], 0 => true], "['attributes' => ['type' => \\Illuminate\\View\\Compilers\\BladeCompiler::sanitizeComponentAttribute(\$type), 'name' => 'email'], 0 => true]"],
    'static values' => [fn () => ['name' => 'email', 'invalid' => false, 'size' => null], "['name' => 'email', 'invalid' => false, 'size' => NULL]"],
]);

test('fails when an unblaze scope holds a placeholder it cannot export', function (Closure $scope) {
    $node = app(Parser::class)->parse('<x-input :type="$type">Content</x-input>')[0];

    mock(BladeRenderer::class)
        ->expects('render')
        ->once()
        ->andReturnUsing(fn ($node, $path, $exportScope) => $exportScope($scope()));

    expect(fn () => (new Foldable($node, '', app(BladeRenderer::class), app(BladeService::class)))->fold())
        ->toThrow(LeftoverPlaceholdersException::class);
})->with([
    'raw copy escaped once' => [fn () => ['type' => 'BLAZE_PLACEHOLDER_0_raw&amp;_&amp;amp;_']],
    'attribute bag copy escaped once' => [fn () => ['type' => 'BLAZE_PLACEHOLDER_0_bag&amp;_&amp;amp;_']],
    'attribute bag copy decoded' => [fn () => ['type' => 'BLAZE_PLACEHOLDER_0_bag&_&_']],
    'attribute bag copy decoded and escaped' => [fn () => ['type' => 'BLAZE_PLACEHOLDER_0_bag&amp;_&amp;_']],
    'concatenated' => [fn () => ['id' => 'field_BLAZE_PLACEHOLDER_0_raw&_&amp;_']],
    'merged copies' => [fn () => ['class' => 'BLAZE_PLACEHOLDER_0_raw&amp;_&amp;amp;_ BLAZE_PLACEHOLDER_0_bag&_&amp;_']],
    'slot' => [fn () => ['slot' => 'BLAZE_PLACEHOLDER_1_']],
    'object' => [fn () => ['attributes' => new \Illuminate\View\ComponentAttributeBag(['type' => 'BLAZE_PLACEHOLDER_0_bag&_&amp;_'])]],
]);

test('fails when a bound attribute was transformed by the component', function (string $rendered) {
    $node = app(Parser::class)->parse('<x-input :type="$type" />')[0];

    mock(BladeRenderer::class)
        ->expects('render')
        ->once()
        ->andReturn($rendered);

    expect(fn () => (new Foldable($node, '', app(BladeRenderer::class), app(BladeService::class)))->fold())
        ->toThrow(LeftoverPlaceholdersException::class);
})->with([
    'escaped twice' => '<input type="BLAZE_PLACEHOLDER_0_raw&amp;amp;_&amp;amp;amp;_">',
    'attribute bag copy escaped' => '<input type="BLAZE_PLACEHOLDER_0_bag&amp;_&amp;amp;_">',
    'uppercased' => '<input type="BLAZE_PLACEHOLDER_0_RAW&AMP;_&AMP;AMP;_">',
    'lowercased' => '<input type="blaze_placeholder_0_raw&amp;_&amp;amp;_">',
    'url encoded' => '<input type="BLAZE_PLACEHOLDER_0_raw%26_%26amp;_">',
    'json encoded' => '<input x-data="{ type: \'BLAZE_PLACEHOLDER_0_raw\u0026_\u0026amp;_\' }">',
    'raw copy decoded' => '<input type="BLAZE_PLACEHOLDER_0_raw&_&_">',
    'attribute bag copy decoded' => '<input type="BLAZE_PLACEHOLDER_0_bag&_&_">',
    'merged with itself' => '<input class="BLAZE_PLACEHOLDER_0_raw&amp;_&amp;amp;_ BLAZE_PLACEHOLDER_0_bag&_&amp;_">',
    'merged with itself after a quote in text' => '<p>12" screen</p><input class="BLAZE_PLACEHOLDER_0_raw&amp;_&amp;amp;_ BLAZE_PLACEHOLDER_0_bag&_&amp;_">',
    'merged with itself in single quotes' => "<input class='BLAZE_PLACEHOLDER_0_raw&amp;_&amp;amp;_ BLAZE_PLACEHOLDER_0_bag&_&amp;_'>",
    'merged with itself after an escaped quote' => '<input x-data="{ a: \\"b\\" }" class="BLAZE_PLACEHOLDER_0_raw&amp;_&amp;amp;_ BLAZE_PLACEHOLDER_0_bag&_&amp;_">',
    'without copy marker' => '<input type="BLAZE_PLACEHOLDER_0_">',
    'without copy marker in attribute bag' => '<input [BLAZE_ATTR:BLAZE_PLACEHOLDER_0_:type]>',
    'attribute bag copy decoded and escaped in attribute bag' => '<input [BLAZE_ATTR:BLAZE_PLACEHOLDER_0_bag&amp;_&amp;_:type]>',
    'raw copy in attribute bag' => '<input [BLAZE_ATTR:BLAZE_PLACEHOLDER_0_raw&_&amp;_:type]>',
    'in php block' => "<?php echo 'BLAZE_PLACEHOLDER_0_raw&_&amp;_'; ?>",
    'unknown placeholder' => '<input type="BLAZE_PLACEHOLDER_7_raw&_&amp;_">',
]);

test('compiles bound attributes passed through attribute bag', function () {
    $node = app(Parser::class)->parse('<x-input :required="$required" />')[0];

    mock(BladeRenderer::class)
        ->expects('render')
        ->once()->withArgs(function (ComponentNode $node) {
            expect($node->render())->toBe('<x-input required="BLAZE_PLACEHOLDER_0_raw&_&amp;_" />');

            return true;
        })
        ->andReturn('<input [BLAZE_ATTR:BLAZE_PLACEHOLDER_0_bag&_&amp;_:required] type="text" >');

    $output = (new Foldable($node, '', app(BladeRenderer::class), app(BladeService::class)))->fold();

    expect($output)->toEqualCollapsingWhitespace(
        sprintf('<input %s type="text" >', join('', [
            '<?php if (($__blazeAttr = $required) !== false && !is_null($__blazeAttr)): ?>',
            'required="<?php echo e($__blazeAttr === true ? \'required\' : $__blazeAttr); ?>"',
            '<?php endif; unset($__blazeAttr); ?>',
        ]))
    );
});

test('restores unbound attributes passed through attribute bag', function () {
    $node = app(Parser::class)->parse('<x-button wire:click="save({{ $id }})" />')[0];

    mock(BladeRenderer::class)
        ->expects('render')
        ->once()->withArgs(function (ComponentNode $node) {
            expect($node->render())->toBe('<x-button wire:click="BLAZE_PLACEHOLDER_0_" />');

            return true;
        })
        ->andReturn('<button [BLAZE_ATTR:BLAZE_PLACEHOLDER_0_:wire:click]></button>');

    $output = (new Foldable($node, '', app(BladeRenderer::class), app(BladeService::class)))->fold();

    expect($output)->toBe('<button wire:click="save({{ $id }})"></button>');
});

test('uses empty strings for true x-data and wire: attributes passed through attribute bag', function (string $attribute) {
    $node = app(Parser::class)->parse('<x-input :'.$attribute.'="$value" />')[0];

    mock(BladeRenderer::class)
        ->expects('render')
        ->once()->withArgs(function (ComponentNode $node) use ($attribute) {
            expect($node->render())->toBe('<x-input '.$attribute.'="BLAZE_PLACEHOLDER_0_raw&_&amp;_" />');

            return true;
        })
        ->andReturn('<input [BLAZE_ATTR:BLAZE_PLACEHOLDER_0_bag&_&amp;_:'.$attribute.']>');

    $output = (new Foldable($node, '', app(BladeRenderer::class), app(BladeService::class)))->fold();

    expect($output)->toBe(implode('', [
        '<input ',
        '<?php if (($__blazeAttr = $value) !== false && !is_null($__blazeAttr)): ?>',
        $attribute.'="<?php echo e($__blazeAttr === true ? \'\' : $__blazeAttr); ?>"',
        '<?php endif; unset($__blazeAttr); ?>',
        '>',
    ]));
})->with(['x-data', 'wire:loading']);

test('handles newlines consumed by attribute php blocks', function () {
    $node = app(Parser::class)->parse('<x-input :required="$required" />')[0];

    mock(BladeRenderer::class)
        ->expects('render')
        ->once()->withArgs(function (ComponentNode $node) {
            expect($node->render())->toBe('<x-input required="BLAZE_PLACEHOLDER_0_raw&_&amp;_" />');

            return true;
        })
        ->andReturn("<input\n[BLAZE_ATTR:BLAZE_PLACEHOLDER_0_bag&_&amp;_:required]\n>");

    $output = (new Foldable($node, '', app(BladeRenderer::class), app(BladeService::class)))->fold();

    expect($output)->toBe(implode('', [
        "<input\n",
        '<?php if (($__blazeAttr = $required) !== false && !is_null($__blazeAttr)): ?>',
        'required="<?php echo e($__blazeAttr === true ? \'required\' : $__blazeAttr); ?>"',
        '<?php endif; unset($__blazeAttr); ?>',
        "\n\n>",
    ]));
});

test('restores named and default slots after rendering', function () {
    $input = <<<'BLADE'
        <x-card>
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
        BLADE;

    $node = app(Parser::class)->parse($input)[0];

    mock(BladeRenderer::class)
        ->expects('render')
        ->once()->withArgs(function (ComponentNode $node) {
            expect($node->render())->toBe(join('', [
                '<x-card>',
                    '<x-slot:header>BLAZE_PLACEHOLDER_0_</x-slot:header>',
                    '<x-slot:footer>BLAZE_PLACEHOLDER_1_</x-slot:footer>',
                    '<x-slot name="slot">BLAZE_PLACEHOLDER_2_</x-slot>',
                '</x-card>',
                ])
            );

            return true;
        })
        ->andReturn(<<<'HTML'
            <div>
                BLAZE_PLACEHOLDER_0_
                <hr>
                BLAZE_PLACEHOLDER_2_
                <hr>
                BLAZE_PLACEHOLDER_1_
            </div>
            HTML
        );

    $output = (new Foldable($node, '', app(BladeRenderer::class), app(BladeService::class)))->fold();

    expect($output)->toEqualCollapsingWhitespace(<<<'HTML'
        <div>
            <?php ob_start(); ?> {{ $title }} <?php echo trim(ob_get_clean()); ?>
            <hr>
            <?php ob_start(); ?> Before {{ $content }} After <?php echo trim(ob_get_clean()); ?>
            <hr>
            <?php ob_start(); ?> {{ $author }} <?php echo trim(ob_get_clean()); ?>
        </div>
        HTML
    );
});

test('does not synthesize a default slot when one is explicit', function () {
    $input = <<<'BLADE'
        <x-card>
            Ignored loose content
            <x-slot name="slot">Explicit content</x-slot>
        </x-card>
        BLADE;

    $node = app(Parser::class)->parse($input)[0];

    mock(BladeRenderer::class)
        ->expects('render')
        ->once()->withArgs(function (ComponentNode $node) {
            expect($node->render())->toBe('<x-card><x-slot name="slot">BLAZE_PLACEHOLDER_0_</x-slot></x-card>');

            return true;
        })
        ->andReturn('<div>BLAZE_PLACEHOLDER_0_</div>');

    $output = (new Foldable($node, '', app(BladeRenderer::class), app(BladeService::class)))->fold();

    expect($output)->toBe('<div><?php ob_start(); ?>Explicit content<?php echo trim(ob_get_clean()); ?></div>');
});

test('handles newlines consumed by slot php blocks', function () {
    $node = app(Parser::class)->parse('<x-card>Content</x-card>')[0];

    mock(BladeRenderer::class)
        ->expects('render')
        ->once()->withArgs(function (ComponentNode $node) {
            expect($node->render())->toBe('<x-card><x-slot name="slot">BLAZE_PLACEHOLDER_0_</x-slot></x-card>');

            return true;
        })
        ->andReturn("<div>BLAZE_PLACEHOLDER_0_\n</div>");

    $output = (new Foldable($node, '', app(BladeRenderer::class), app(BladeService::class)))->fold();

    expect($output)->toBe("<div><?php ob_start(); ?>Content<?php echo trim(ob_get_clean()); ?>\n\n</div>");
});

test('wraps output with aware macros if descendants use aware', function () {
    $node = app(Parser::class)->parse('<x-layout theme="dark" />')[0];

    $node->hasAwareDescendants = true;

    mock(BladeRenderer::class)
        ->expects('render')
        ->once()->withArgs(function (ComponentNode $node) {
            expect($node->render())->toBe('<x-layout theme="dark" />');

            return true;
        })
        ->andReturn('<div></div>');

    $output = (new Foldable($node, '', app(BladeRenderer::class), app(BladeService::class)))->fold();

    expect($output)->toEqualCollapsingWhitespace(join('', [
        '<?php $__blaze->pushData([\'theme\' => \'dark\']); $__env->pushConsumableComponentData([\'theme\' => \'dark\']); ?>',
        '<div></div>',
        '<?php $__blaze->popData(); $__env->popConsumableComponentData(); ?>',
    ]));
});

test('compiles dynamic attributes in aware macros', function () {
    $node = app(Parser::class)->parse('<x-layout :theme="$theme" />')[0];

    $node->hasAwareDescendants = true;

    mock(BladeRenderer::class)
        ->expects('render')
        ->once()->withArgs(function (ComponentNode $node) {
            expect($node->render())->toBe('<x-layout theme="BLAZE_PLACEHOLDER_0_raw&_&amp;_" />');

            return true;
        })
        ->andReturn('<div></div>');

    $output = (new Foldable($node, '', app(BladeRenderer::class), app(BladeService::class)))->fold();

    expect($output)->toEqualCollapsingWhitespace(join('', [
        '<?php $__blaze->pushData([\'theme\' => $theme]); $__env->pushConsumableComponentData([\'theme\' => $theme]); ?>',
        '<div></div>',
        '<?php $__blaze->popData(); $__env->popConsumableComponentData(); ?>',
    ]));
});

test('compiles echo attributes in aware macros', function () {
    $node = app(Parser::class)->parse('<x-layout theme="dark-{{ $variant }}" />')[0];
    $node->hasAwareDescendants = true;

    mock(BladeRenderer::class)
        ->expects('render')
        ->once()->withArgs(function (ComponentNode $node) {
            expect($node->render())->toBe('<x-layout theme="BLAZE_PLACEHOLDER_0_" />');

            return true;
        })
        ->andReturn('<div></div>');

    $output = (new Foldable($node, '', app(BladeRenderer::class), app(BladeService::class)))->fold();

    expect($output)->toEqualCollapsingWhitespace(join('', [
        '<?php $__blaze->pushData([\'theme\' => \'dark-\'.e($variant)]); $__env->pushConsumableComponentData([\'theme\' => \'dark-\'.e($variant)]); ?>',
        '<div></div>',
        '<?php $__blaze->popData(); $__env->popConsumableComponentData(); ?>',
    ]));
});

test('does not add aware macros to components without attributes', function () {
    $node = app(Parser::class)->parse('<x-layout />')[0];
    $node->hasAwareDescendants = true;

    mock(BladeRenderer::class)
        ->expects('render')
        ->once()->withArgs(function (ComponentNode $node) {
            expect($node->render())->toBe('<x-layout />');

            return true;
        })
        ->andReturn('<div></div>');

    expect((new Foldable($node, '', app(BladeRenderer::class), app(BladeService::class)))->fold())
        ->toBe('<div></div>');
});

test('does not add aware macros for inherited attributes only', function () {
    $node = app(Parser::class)->parse('<x-layout />')[0];
    $node->hasAwareDescendants = true;
    $node->setParentsAttributes(app(AttributeParser::class)->parse('theme="dark"'));

    mock(BladeRenderer::class)
        ->expects('render')
        ->once()->withArgs(function (ComponentNode $node) {
            expect($node->render())->toBe('<x-layout />');
            expect($node->parentsAttributes['theme']->render())->toBe('theme="dark"');

            return true;
        })
        ->andReturn('<div></div>');

    expect((new Foldable($node, '', app(BladeRenderer::class), app(BladeService::class)))->fold())
        ->toBe('<div></div>');
});

test('does not synthesize a default slot for whitespace-only loose content', function () {
    $node = app(Parser::class)->parse('<x-card> </x-card>')[0];

    mock(BladeRenderer::class)
        ->expects('render')
        ->once()->withArgs(function (ComponentNode $node) {
            expect($node->render())->toBe('<x-card></x-card>');

            return true;
        })
        ->andReturn('<div></div>');

    $output = (new Foldable($node, '', app(BladeRenderer::class), app(BladeService::class)))->fold();

    expect($output)->toBe('<div></div>');
});

test('does not add a placeholder for a whitespace-only slot', function () {
    $node = app(Parser::class)->parse('<x-card><x-slot:footer> </x-slot:footer></x-card>')[0];

    mock(BladeRenderer::class)
        ->expects('render')
        ->once()->withArgs(function (ComponentNode $node) {
            expect($node->render())->toBe('<x-card><x-slot:footer></x-slot:footer></x-card>');

            return true;
        })
        ->andReturn('<div></div>');

    $output = (new Foldable($node, '', app(BladeRenderer::class), app(BladeService::class)))->fold();

    expect($output)->toBe('<div></div>');
});
