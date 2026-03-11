<?php

use Livewire\Blaze\Parser\Parser;
use Livewire\Blaze\Compiler\Compiler;
use Livewire\Blaze\Support\Utils;

test('compiles self-closing components', function () {
    $input = '<x-input type="text" :disabled="$disabled" />';

    $node = app(Parser::class)->parse($input)[0];
    $compiled = app(Compiler::class)->compile($node);

    $path = fixture_path('views/components/input.blade.php');
    $hash = Utils::hash($path);

    expect($compiled->render())->toEqualCollapsingWhitespace(join('', [
        '<?php $__blaze->ensureRequired(\''. $path .'\', $__blaze->compiledPath.\'/'. $hash .'.php\'); ?> ',
        '<?php $__blaze->pushData([\'type\' => \'text\',\'disabled\' => $disabled]); ?> ',
        '<?php _'. $hash .'($__blaze, [\'type\' => \'text\',\'disabled\' => $disabled], [], [\'disabled\'], isset($this) ? $this : null); ?> ',
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
        '<?php $__blaze->ensureRequired(\''. $path .'\', $__blaze->compiledPath.\'/'. $hash .'.php\'); ?> ',
        '<?php if (isset($__slots'. $hash .')) $__slotsOriginal = $__slots'. $hash .'; ?> ',
        '<?php if (isset($__attrs'. $hash .')) $__attrsOriginal = $__attrs'. $hash .'; ?> ',
        '<?php $__attrs'. $hash .' = [\'class\' => \'mt-8\']; ?> ',
        '<?php $__slots'. $hash .' = []; ?> ',
        '<?php ob_start(); ?> Body <?php $__slots'. $hash .'[\'slot\'] = new \Illuminate\View\ComponentSlot(trim(ob_get_clean()), []); ?> ',
        '<?php ob_start(); ?> Header <?php $__slots'. $hash .'[\'header\'] = new \Illuminate\View\ComponentSlot(trim(ob_get_clean()), [\'class\' => \'p-2\']); ?> ',
        '<?php ob_start(); ?> Footer <?php $__slots'. $hash .'[\'footer\'] = new \Illuminate\View\ComponentSlot(trim(ob_get_clean()), [\'class\' => \'mt-4\']); ?> ',
        '<?php $__blaze->pushData($__attrs'. $hash .'); ?> ',
        '<?php $__blaze->pushSlots($__slots'. $hash .'); ?> ',
        '<?php _'. $hash .'($__blaze, $__attrs'. $hash .', $__slots'. $hash .', [], isset($this) ? $this : null); ?> ',
        '<?php if (isset($__slotsOriginal)) { $__slots'. $hash .' = $__slotsOriginal; unset($__slotsOriginal); } ?> ',
        '<?php if (isset($__attrsOriginal)) { $__attrs'. $hash .' = $__attrsOriginal; unset($__attrsOriginal); } ?> ',
        '<?php $__blaze->popData(); ?>',
    ]));
});

test('compiles delegate components', function () {
    $input = '<flux:delegate-component component="card" />';

    $node = app(Parser::class)->parse($input)[0];
    $compiled = app(Compiler::class)->compile($node);

    expect($compiled->render())->toEqualCollapsingWhitespace(join('', [
        '<?php $__resolved = $__blaze->resolve(\'flux::\' . card); ?> ',
        '<?php $__blaze->pushData($attributes->all()); ?> ',
        '<?php if ($__resolved !== false): ?> ',
        '<?php (\'_\' . $__resolved)($__blaze, $attributes->all(), $__blaze->mergedComponentSlots(), [], isset($this) ? $this : null); ?> ',
        '<?php else: ?> ',
        '<flux:delegate-component component="card" /> ',
        '<?php endif; ?> ',
        '<?php $__blaze->popData(); ?> ',
        '<?php unset($__resolved) ?>',
    ]));
});