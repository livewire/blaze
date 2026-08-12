<?php

use Livewire\Blaze\BladeRenderer;
use Livewire\Blaze\BladeService;
use Livewire\Blaze\Folder\Foldable;
use Livewire\Blaze\Parser\Nodes\ComponentNode;
use Livewire\Blaze\Parser\Parser;
use Livewire\Blaze\Support\AttributeParser;

use function Pest\Laravel\mock;

test('replaces and restores bound attributes', function () {
    $node = app(Parser::class)->parse('<x-input :type="$type" />')->nodes[0];

    mock(BladeRenderer::class)
        ->expects('render')
        ->once()->withArgs(function (ComponentNode $node) {
            expect($node->render())->toBe('<x-input type="BLAZE_PLACEHOLDER_0_" />');

            return true;
        })
        ->andReturn('<input type="BLAZE_PLACEHOLDER_0_" >');

    $output = (new Foldable($node, '', app(BladeRenderer::class), app(BladeService::class)))->fold();

    expect($output)->toEqualCollapsingWhitespace('<input type="{{ $type }}" >');
});

test('preserves bound attributes with static constant values', function (string $value) {
    $node = app(Parser::class)->parse('<x-input :disabled="' . $value . '" />')->nodes[0];

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
    $node = app(Parser::class)->parse('<x-input />')->nodes[0];

    $node->setParentsAttributes(
        app(AttributeParser::class)->parse(':type="$type"')
    );

    mock(BladeRenderer::class)
        ->expects('render')
        ->once()->withArgs(function (ComponentNode $node) {
            expect($node->render())->toBe('<x-input />');
            expect($node->parentsAttributes['type']->render())->toBe('type="BLAZE_PLACEHOLDER_0_"');

            return true;
        })
        ->andReturn('<input type="BLAZE_PLACEHOLDER_0_">');

    $output = (new Foldable($node, '', app(BladeRenderer::class), app(BladeService::class)))->fold();

    expect($output)->toEqualCollapsingWhitespace('<input type="{{ $type }}">');
});

test('restores every occurrence of a dynamic attribute placeholder', function () {
    $node = app(Parser::class)->parse('<x-button wire:click="save({{ $id }})" />')->nodes[0];

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

test('restores bound attributes inside php blocks as raw expressions', function () {
    $node = app(Parser::class)->parse('<x-input :type="$type" />')->nodes[0];

    mock(BladeRenderer::class)
        ->expects('render')
        ->once()->withArgs(function (ComponentNode $node) {
            expect($node->render())->toBe('<x-input type="BLAZE_PLACEHOLDER_0_" />');

            return true;
        })
        ->andReturn("<?php echo strtoupper('BLAZE_PLACEHOLDER_0_'); ?>");

    $output = (new Foldable($node, '', app(BladeRenderer::class), app(BladeService::class)))->fold();

    expect($output)->toBe('<?php echo strtoupper($type); ?>');
});

test('compiles echo attributes restored inside php blocks', function () {
    $node = app(Parser::class)->parse('<x-layout theme="dark-{{ $variant }}" />')->nodes[0];

    mock(BladeRenderer::class)
        ->expects('render')
        ->once()->withArgs(function (ComponentNode $node) {
            expect($node->render())->toBe('<x-layout theme="BLAZE_PLACEHOLDER_0_" />');

            return true;
        })
        ->andReturn("<?php echo 'BLAZE_PLACEHOLDER_0_'; ?>");

    $output = (new Foldable($node, '', app(BladeRenderer::class), app(BladeService::class)))->fold();

    expect($output)->toBe("<?php echo 'dark-'.e(\$variant); ?>");
});

test('compiles bound attributes passed through attribute bag', function () {
    $node = app(Parser::class)->parse('<x-input :required="$required" />')->nodes[0];

    mock(BladeRenderer::class)
        ->expects('render')
        ->once()->withArgs(function (ComponentNode $node) {
            expect($node->render())->toBe('<x-input required="BLAZE_PLACEHOLDER_0_" />');

            return true;
        })
        ->andReturn('<input [BLAZE_ATTR:BLAZE_PLACEHOLDER_0_:required] type="text" >');

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
    $node = app(Parser::class)->parse('<x-button wire:click="save({{ $id }})" />')->nodes[0];

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
    $node = app(Parser::class)->parse('<x-input :'.$attribute.'="$value" />')->nodes[0];

    mock(BladeRenderer::class)
        ->expects('render')
        ->once()->withArgs(function (ComponentNode $node) use ($attribute) {
            expect($node->render())->toBe('<x-input '.$attribute.'="BLAZE_PLACEHOLDER_0_" />');

            return true;
        })
        ->andReturn('<input [BLAZE_ATTR:BLAZE_PLACEHOLDER_0_:'.$attribute.']>');

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
    $node = app(Parser::class)->parse('<x-input :required="$required" />')->nodes[0];

    mock(BladeRenderer::class)
        ->expects('render')
        ->once()->withArgs(function (ComponentNode $node) {
            expect($node->render())->toBe('<x-input required="BLAZE_PLACEHOLDER_0_" />');

            return true;
        })
        ->andReturn("<input\n[BLAZE_ATTR:BLAZE_PLACEHOLDER_0_:required]\n>");

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

    $node = app(Parser::class)->parse($input)->nodes[0];

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

    $node = app(Parser::class)->parse($input)->nodes[0];

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
    $node = app(Parser::class)->parse('<x-card>Content</x-card>')->nodes[0];

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
    $node = app(Parser::class)->parse('<x-layout theme="dark" />')->nodes[0];

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
    $node = app(Parser::class)->parse('<x-layout :theme="$theme" />')->nodes[0];

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
        '<?php $__blaze->pushData([\'theme\' => $theme]); $__env->pushConsumableComponentData([\'theme\' => $theme]); ?>',
        '<div></div>',
        '<?php $__blaze->popData(); $__env->popConsumableComponentData(); ?>',
    ]));
});

test('compiles echo attributes in aware macros', function () {
    $node = app(Parser::class)->parse('<x-layout theme="dark-{{ $variant }}" />')->nodes[0];
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
    $node = app(Parser::class)->parse('<x-layout />')->nodes[0];
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
    $node = app(Parser::class)->parse('<x-layout />')->nodes[0];
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
