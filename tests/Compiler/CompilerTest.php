<?php

use Livewire\Blaze\Parser\Parser;
use Livewire\Blaze\Compiler\Compiler;
use Livewire\Blaze\Config;
use Livewire\Blaze\Parser\Nodes\ComponentNode;
use Livewire\Blaze\Support\Utils;

test('compiles self-closing components', function () {
    $input = '<x-input type="text" :disabled="$disabled" />';

    $node = app(Parser::class)->parse($input)[0];
    $compiled = app(Compiler::class)->compile($node);

    $path = fixture_path('views/components/input.blade.php');
    $hash = Utils::hash($path);

    expect($compiled->render())->toEqualCollapsingWhitespace(join('', [
        '<?php if (!function_exists(\'_'. $hash .'\')) { require $__blaze->compile(\''. $path .'\'); } ?> ',
        '<?php $__componentData = [\'type\' => \'text\',\'disabled\' => $disabled]; ?> ',
        '<?php $__blaze->pushData($__componentData); ?> ',
        '<?php _'. $hash .'($__blaze, $__componentData, [\'type\' => \'text\', \'disabled\' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($__componentData[\'disabled\'])], [], $__this ?? (isset($this) ? $this : null)); ?> ',
        '<?php $__blaze->popData(); ?>',
    ]));
});

test('compiles components', function () {
    $input = <<<'BLADE'
        <x-card class="mt-8">
            <x-slot:header class="p-2">
                Header
            </x-slot:header>
            Body
            <x-slot:footer class="mt-4">
                Footer
            </x-slot:footer>
        </x-card>
        BLADE
    ;

    $node = app(Parser::class)->parse($input)[0];
    $compiled = app(Compiler::class)->compile($node);

    $path = fixture_path('views/components/card.blade.php');
    $hash = Utils::hash($path);

    expect($compiled->render())->toEqualCollapsingWhitespace(join('', [
        '<?php if (!function_exists(\'_'. $hash .'\')) { require $__blaze->compile(\''. $path .'\'); } ?> ',
        '<?php if (isset($__slots'. $hash .')) { $__slotsStack'. $hash .'[] = $__slots'. $hash .'; } ?> ',
        '<?php if (isset($__data'. $hash .')) { $__dataStack'. $hash .'[] = $__data'. $hash .'; } ?> ',
        '<?php $__data'. $hash .' = [\'class\' => \'mt-8\']; ?> ',
        '<?php $__slots'. $hash .' = []; ?> ',
        '<?php $__blaze->pushData($__data'. $hash .'); ?> ',
        '<?php ob_start(); ?> ',
        '<?php ob_start(); ?> Header <?php $__slots'. $hash .'[\'header\'] = new \Illuminate\View\ComponentSlot(trim(ob_get_clean()), [\'class\' => \'p-2\']); ?> ',
        'Body ',
        '<?php ob_start(); ?> Footer <?php $__slots'. $hash .'[\'footer\'] = new \Illuminate\View\ComponentSlot(trim(ob_get_clean()), [\'class\' => \'mt-4\']); ?> ',
        '<?php $__slots'. $hash .'[\'slot\'] = new \Illuminate\View\ComponentSlot(trim(ob_get_clean()), []); ?> ',
        '<?php $__blaze->pushSlots($__slots'. $hash .'); ?> ',
        '<?php _'. $hash .'($__blaze, $__data'. $hash .', [\'class\' => \'mt-8\'], $__slots'. $hash .', $__this ?? (isset($this) ? $this : null)); ?> ',
        '<?php if (! empty($__slotsStack'. $hash .')) { $__slots'. $hash .' = array_pop($__slotsStack'. $hash .'); } ?> ',
        '<?php if (! empty($__dataStack'. $hash .')) { $__data'. $hash .' = array_pop($__dataStack'. $hash .'); } ?> ',
        '<?php $__blaze->popData(); ?>',
    ]));
});

test('compiles delegate components', function () {
    $input = '<flux:delegate-component component="card" />';

    $node = app(Parser::class)->parse($input)[0];
    $compiled = app(Compiler::class)->compile($node);

    expect($compiled->render())->toEqualCollapsingWhitespace(join('', [
        '<?php $__resolved = $__blaze->resolve(\'flux::\' . card); ?> ',
        '<?php $__delegatedData = $__delegateData ?? $__env->getCurrentComponentData(); ?> ',
        '<?php $__blaze->pushData($__delegatedData); ?> ',
        '<?php if ($__resolved !== false): ?> ',
        '<?php (\'_\' . $__resolved)($__blaze, $__delegatedData, $attributes->getAttributes(), $__blaze->mergedComponentSlots(), $__this ?? (isset($this) ? $this : null)); ?> ',
        '<?php else: ?> ',
        '<?php if (! Flux::componentExists((card))) throw new \Exception(\'Flux component [\' . (card) . \'] does not exist.\'); ?> ',
        '@component(\'Illuminate\View\AnonymousComponent\', \'flux::\' . (card), [\'view\' => (app()->version() >= 12 ? hash(\'xxh128\', \'flux\') : md5(\'flux\')) . \'::\' . (card), \'data\' => array_merge($__delegatedData, $__blaze->mergedComponentSlots())]) ',
        '<?php $component->withAttributes($attributes->getAttributes()); ?> ',
        '@endComponentClass ',
        '<?php endif; ?> ',
        '<?php $__blaze->popData(); ?> ',
        '<?php unset($__resolved, $__delegatedData) ?>',
    ]));
});

test('does not compile components with blaze directive override set to false', function () {
    $input = '<x-compile-false />';

    app(Config::class)->add(fixture_path('views/components'));

    $node = app(Parser::class)->parse($input)[0];
    $compiled = app(Compiler::class)->compile($node);

    expect($compiled)->toBeInstanceOf(ComponentNode::class);
});
