<?php

use Livewire\Blaze\Parser\Parser;
use Livewire\Blaze\Compiler\Compiler;
use Livewire\Blaze\Support\Utils;

test('compiles self-closing components', function () {
    $input = '<x-input type="text" :disabled="$disabled" />';

    $node = app(Parser::class)->parse($input)[0];
    $compiled = app(Compiler::class)->compile($node);

    $path = fixture_path('components/input.blade.php');
    $hash = Utils::hash($path);

    expect($compiled->render())->toEqualCollapsingWhitespace(join('', [
        '<?php $__blaze->ensureCompiled(\''. $path .'\', $__blaze->compiledPath.\'/'. $hash .'.php\'); ?> ',
        '<?php require_once $__blaze->compiledPath.\'/'. $hash .'.php\'; ?> ',
        '<?php $__blaze->pushData([\'type\' => \'text\',\'disabled\' => $disabled]); ?> ',
        '<?php _'. $hash .'($__blaze, [\'type\' => \'text\',\'disabled\' => $disabled], [], [\'disabled\'], isset($this) ? $this : null); ?> ',
        '<?php $__blaze->popData(); ?>',
    ]));
});

test('compiles slots', function () {
    $input = <<<'BLADE'
        <x-card>
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

    $path = fixture_path('components/card.blade.php');
    $hash = Utils::hash($path);

    expect($compiled->render())->toEqualCollapsingWhitespace(join('', [
        '<?php $__blaze->ensureCompiled(\''. $path .'\', $__blaze->compiledPath.\'/'. $hash .'.php\'); ?> ',
        '<?php require_once $__blaze->compiledPath.\'/'. $hash .'.php\'; ?> ',
        '<?php $__attrs'. $hash .' = []; ?> ',
        '<?php $__blaze->pushData($__attrs'. $hash .'); ?> ',
        '<?php $slots'. $hash .' = []; ?> ',
        '<?php ob_start(); ?> Body <?php $slots'. $hash .'[\'slot\'] = new \Illuminate\View\ComponentSlot(trim(ob_get_clean()), []); ?> ',
        '<?php ob_start(); ?> Header <?php $slots'. $hash .'[\'header\'] = new \Illuminate\View\ComponentSlot(trim(ob_get_clean()), [\'class\' => \'p-2\']); ?> ',
        '<?php ob_start(); ?> Footer <?php $slots'. $hash .'[\'footer\'] = new \Illuminate\View\ComponentSlot(trim(ob_get_clean()), [\'class\' => \'mt-4\']); ?> ',
        '<?php $__blaze->pushSlots($slots'. $hash .'); ?> ',
        '<?php _'. $hash .'($__blaze, $__attrs'. $hash .', $slots'. $hash .', [], isset($this) ? $this : null); ?> ',
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
        '<?php require_once $__blaze->compiledPath . \'/\' . $__resolved . \'.php\'; ?> ',
        '<?php (\'_\' . $__resolved)($__blaze, $attributes->all(), $__blaze->mergedComponentSlots(), [], isset($this) ? $this : null); ?> ',
        '<?php else: ?> ',
        '<flux:delegate-component component="card" /> ',
        '<?php endif; ?> ',
        '<?php $__blaze->popData(); ?> ',
        '<?php unset($__resolved) ?> ',
    ]));
});