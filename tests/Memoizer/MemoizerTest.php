<?php

use Livewire\Blaze\Memoizer\Memoizer;
use Livewire\Blaze\Parser\Parser;
use Livewire\Blaze\Support\Utils;
use Livewire\Blaze\Parser\Nodes\ComponentNode;
use Livewire\Blaze\Parser\Nodes\TextNode;
use Livewire\Blaze\Config;

test('memoizes self-closing components', function () {
    $input = '<x-memoizable.avatar :src="$user->avatar" />';

    $node = app(Parser::class)->parse($input)[0];
    $memoized = app(Memoizer::class)->memoize($node);

    $path = fixture_path('components/memoizable/avatar.blade.php');
    $hash = Utils::hash($path);

    expect($memoized->render())->toEqualCollapsingWhitespace(join('', [
        '<?php $blaze_memoized_key = \Livewire\Blaze\Memoizer\Memo::key("memoizable.avatar", [\'src\' => $user->avatar]); ?>',
        '<?php if ($blaze_memoized_key !== null && \Livewire\Blaze\Memoizer\Memo::has($blaze_memoized_key)) : ?>',
        '<?php echo \Livewire\Blaze\Memoizer\Memo::get($blaze_memoized_key); ?>',
        '<?php else : ?>',
        '<?php ob_start(); ?>',
        '<?php $__blaze->ensureCompiled(\''. $path .'\', $__blaze->compiledPath.\'/'. $hash .'.php\'); ?> ',
        '<?php require_once $__blaze->compiledPath.\'/'. $hash .'.php\'; ?> ',
        '<?php $__blaze->pushData([\'src\' => $user->avatar]); ?> ',
        '<?php _'. $hash .'($__blaze, [\'src\' => $user->avatar], [], [\'src\'], isset($this) ? $this : null); ?> ',
        '<?php $__blaze->popData(); ?>',
        '<?php $blaze_memoized_html = ob_get_clean(); ?>',
        '<?php if ($blaze_memoized_key !== null) { \Livewire\Blaze\Memoizer\Memo::put($blaze_memoized_key, $blaze_memoized_html); } ?>',
        '<?php echo $blaze_memoized_html; ?><?php endif; ?>'
    ]));
});

test('handles echo attributes', function () {
    $input = '<x-memoizable.avatar src="https://avatars.com/{{ $user->username }}" />';

    $node = app(Parser::class)->parse($input)[0];
    $memoized = app(Memoizer::class)->memoize($node);

    expect($memoized->render())->toContain('[\'src\' => \'https://avatars.com/\'.e($user->username)]');
});

test('does not memoize non-self-closing components', function () {
    $input = <<<'BLADE'
        <x-memoizable.avatar>
            <img src="https://avatars.com/{{ $user->username }}" />
        </x-memoizable.avatar>
        BLADE
    ;

    $node = app(Parser::class)->parse($input)[0];
    $memoized = app(Memoizer::class)->memoize($node);

    expect($memoized)->toBeInstanceOf(ComponentNode::class);
});

test('memoizes components without blaze directive if enabled in config', function () {
    $input = '<x-memoizable.avatar-no-blaze />';

    app(Config::class)->add(fixture_path('components/memoizable'), memo: true);

    $node = app(Parser::class)->parse($input)[0];
    $memoized = app(Memoizer::class)->memoize($node);

    expect($memoized)->toBeInstanceOf(TextNode::class);
});