<?php

use Livewire\Blaze\BladeRenderer;
use Livewire\Blaze\BladeService;
use Livewire\Blaze\Folder\Foldable;
use Livewire\Blaze\Parser\Parser;
use Livewire\Blaze\Support\AttributeParser;

use function Pest\Laravel\mock;

test('replaces and restores bound attributes', function () {
    $node = app(Parser::class)->parse('<x-input :type="$type" />')[0];

    mock(BladeRenderer::class)
        ->expects('render')
        ->once()->withArgs(function (string $path, array $data, array $attributes) {
            expect($data)->toBe(['type' => 'BLAZE_PLACEHOLDER_PROP_0_']);
            expect($attributes)->toBe(['type' => 'BLAZE_PLACEHOLDER_ATTR_1_']);

            return true;
        })
        ->andReturn('<input type="BLAZE_PLACEHOLDER_ATTR_1_" >');

    $output = (new Foldable($node, '', app(BladeRenderer::class), app(BladeService::class)))->fold();

    expect($output)->toEqualCollapsingWhitespace('<input type="{{ $type }}" >');
});

test('preserves bound attributes with static constant values', function (string $value, ?bool $expected) {
    $node = app(Parser::class)->parse('<x-input :disabled="' . $value . '" />')[0];

    mock(BladeRenderer::class)
        ->expects('render')
        ->once()->withArgs(function (string $path, array $data, array $attributes) use ($expected) {
            expect($attributes)->toBe(['disabled' => $expected]);

            return true;
        })
        ->andReturn('');

    (new Foldable($node, '', app(BladeRenderer::class), app(BladeService::class)))->fold();
})->with([['false', false], ['true', true], ['null', null]]);

test('replaces parents attributes', function () {
    $node = app(Parser::class)->parse('<x-input />')[0];

    $node->setParentsAttributes(
        app(AttributeParser::class)->parse(':type="$type"')
    );

    mock(BladeRenderer::class)
        ->expects('render')
        ->once()->withArgs(function (string $path, array $data, array $attributes, array $slots, array $awareData) {
            expect($attributes)->toBe([]);
            expect($awareData)->toBe(['type' => 'BLAZE_PLACEHOLDER_PROP_0_']);

            return true;
        })
        ->andReturn('<input type="BLAZE_PLACEHOLDER_PROP_0_">');

    $output = (new Foldable($node, '', app(BladeRenderer::class), app(BladeService::class)))->fold();

    expect($output)->toEqualCollapsingWhitespace('<input type="{{ $type }}">');
});

test('restores every occurrence of a dynamic attribute placeholder', function () {
    $node = app(Parser::class)->parse('<x-button wire:click="save({{ $id }})" />')[0];

    mock(BladeRenderer::class)
        ->expects('render')
        ->once()->withArgs(function (string $path, array $data, array $attributes) {
            expect($attributes)->toBe(['wire:click' => 'BLAZE_PLACEHOLDER_ATTR_1_']);

            return true;
        })
        ->andReturn('<button wire:target="BLAZE_PLACEHOLDER_ATTR_1_" wire:click="BLAZE_PLACEHOLDER_ATTR_1_"></button>');

    $output = (new Foldable($node, '', app(BladeRenderer::class), app(BladeService::class)))->fold();

    expect($output)->toEqualCollapsingWhitespace(
        '<button wire:target="save({{ $id }})" wire:click="save({{ $id }})"></button>'
    );
});

test('restores bound attributes inside php blocks as raw expressions', function () {
    $node = app(Parser::class)->parse('<x-input :type="$type" />')[0];

    mock(BladeRenderer::class)
        ->expects('render')
        ->once()->withArgs(function (string $path, array $data, array $attributes) {
            expect($data)->toBe(['type' => 'BLAZE_PLACEHOLDER_PROP_0_']);

            return true;
        })
        ->andReturn("<?php echo strtoupper('BLAZE_PLACEHOLDER_PROP_0_'); ?>");

    $output = (new Foldable($node, '', app(BladeRenderer::class), app(BladeService::class)))->fold();

    expect($output)->toBe('<?php echo strtoupper($type); ?>');
});

test('restores bound attributes from the attribute bag inside php blocks as escaped expressions', function () {
    $node = app(Parser::class)->parse('<x-input :type="$type" />')[0];

    mock(BladeRenderer::class)
        ->expects('render')
        ->once()
        ->andReturn("<?php \$scope = array ('type' => 'BLAZE_PLACEHOLDER_ATTR_1_'); ?>");

    $output = (new Foldable($node, '', app(BladeRenderer::class), app(BladeService::class)))->fold();

    expect($output)->toBe("<?php \$scope = array ('type' => \\Illuminate\\View\\Compilers\\BladeCompiler::sanitizeComponentAttribute(\$type)); ?>");
});

test('compiles echo attributes restored inside php blocks', function () {
    $node = app(Parser::class)->parse('<x-layout theme="dark-{{ $variant }}" />')[0];

    mock(BladeRenderer::class)
        ->expects('render')
        ->once()->withArgs(function (string $path, array $data, array $attributes) {
            expect($data)->toBe(['theme' => 'BLAZE_PLACEHOLDER_PROP_0_']);

            return true;
        })
        ->andReturn("<?php echo 'BLAZE_PLACEHOLDER_PROP_0_'; ?>");

    $output = (new Foldable($node, '', app(BladeRenderer::class), app(BladeService::class)))->fold();

    expect($output)->toBe("<?php echo 'dark-'.e(\$variant); ?>");
});

test('compiles bound attributes passed through attribute bag', function () {
    $node = app(Parser::class)->parse('<x-input :required="$required" />')[0];

    mock(BladeRenderer::class)
        ->expects('render')
        ->once()->withArgs(function (string $path, array $data, array $attributes) {
            expect($attributes)->toBe(['required' => 'BLAZE_PLACEHOLDER_ATTR_1_']);

            return true;
        })
        ->andReturn('<input [BLAZE_ATTR:BLAZE_PLACEHOLDER_ATTR_1_:required] type="text" >');

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
        ->once()->withArgs(function (string $path, array $data, array $attributes) {
            expect($attributes)->toBe(['wire:click' => 'BLAZE_PLACEHOLDER_ATTR_1_']);

            return true;
        })
        ->andReturn('<button [BLAZE_ATTR:BLAZE_PLACEHOLDER_ATTR_1_:wire:click]></button>');

    $output = (new Foldable($node, '', app(BladeRenderer::class), app(BladeService::class)))->fold();

    expect($output)->toBe('<button wire:click="save({{ $id }})"></button>');
});

test('uses empty strings for true x-data and wire: attributes passed through attribute bag', function (string $attribute) {
    $node = app(Parser::class)->parse('<x-input :'.$attribute.'="$value" />')[0];

    mock(BladeRenderer::class)
        ->expects('render')
        ->once()->withArgs(function (string $path, array $data, array $attributes) use ($attribute) {
            expect($attributes)->toBe([$attribute => 'BLAZE_PLACEHOLDER_ATTR_1_']);

            return true;
        })
        ->andReturn('<input [BLAZE_ATTR:BLAZE_PLACEHOLDER_ATTR_1_:'.$attribute.']>');

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
        ->once()->withArgs(function (string $path, array $data, array $attributes) {
            expect($attributes)->toBe(['required' => 'BLAZE_PLACEHOLDER_ATTR_1_']);

            return true;
        })
        ->andReturn("<input\n[BLAZE_ATTR:BLAZE_PLACEHOLDER_ATTR_1_:required]\n>");

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
        ->once()->withArgs(function (string $path, array $data, array $attributes, array $slots) {
            expect($slots)->toBe([
                'header' => 'BLAZE_PLACEHOLDER_0_',
                'footer' => 'BLAZE_PLACEHOLDER_1_',
                'slot' => 'BLAZE_PLACEHOLDER_2_',
            ]);

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
        ->once()->withArgs(function (string $path, array $data, array $attributes, array $slots) {
            expect($slots)->toBe(['slot' => 'BLAZE_PLACEHOLDER_0_']);

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
        ->once()->withArgs(function (string $path, array $data, array $attributes, array $slots) {
            expect($slots)->toBe(['slot' => 'BLAZE_PLACEHOLDER_0_']);

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
        ->once()->withArgs(function (string $path, array $data, array $attributes) {
            expect($attributes)->toBe(['theme' => 'dark']);

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
        ->once()->withArgs(function (string $path, array $data, array $attributes) {
            expect($attributes)->toBe(['theme' => 'BLAZE_PLACEHOLDER_ATTR_1_']);

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
        ->once()->withArgs(function (string $path, array $data, array $attributes) {
            expect($attributes)->toBe(['theme' => 'BLAZE_PLACEHOLDER_ATTR_1_']);

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
        ->once()->withArgs(function (string $path, array $data, array $attributes) {
            expect($attributes)->toBe([]);

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
        ->once()->withArgs(function (string $path, array $data, array $attributes, array $slots, array $awareData) {
            expect($attributes)->toBe([]);
            expect($awareData)->toBe(['theme' => 'dark']);

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
        ->once()->withArgs(function (string $path, array $data, array $attributes, array $slots) {
            expect($slots)->toBe([]);

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
        ->once()->withArgs(function (string $path, array $data, array $attributes, array $slots) {
            expect($slots)->toBe(['footer' => '']);

            return true;
        })
        ->andReturn('<div></div>');

    $output = (new Foldable($node, '', app(BladeRenderer::class), app(BladeService::class)))->fold();

    expect($output)->toBe('<div></div>');
});
