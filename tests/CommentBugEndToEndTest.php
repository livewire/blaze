<?php

use Livewire\Blaze\Parser\Tokenizer;
use Livewire\Blaze\Parser\Tokens\TagSelfCloseToken;
use Livewire\Blaze\Parser\Tokens\TextToken;

// ==========================================
// Tokenizer: skips component tags inside PHP blocks
// ==========================================

test('tokenizer skips component inside PHP single-line comment', function () {
    $tokenizer = new Tokenizer();

    $input = <<<'TPL'
<?php // <x-button /> ?>
<div>hello</div>
TPL;

    $tokens = $tokenizer->tokenize($input);

    $types = array_map(fn($t) => class_basename($t), $tokens);

    expect($types)->not->toContain('TagSelfCloseToken');
    expect($types)->not->toContain('TagOpenToken');
    expect($types)->each->toBe('TextToken');
});

test('tokenizer skips component inside PHP block comment', function () {
    $tokenizer = new Tokenizer();

    $input = <<<'TPL'
<?php /* <x-button /> */ ?>
<div>hello</div>
TPL;

    $tokens = $tokenizer->tokenize($input);

    $types = array_map(fn($t) => class_basename($t), $tokens);

    expect($types)->not->toContain('TagSelfCloseToken');
    expect($types)->not->toContain('TagOpenToken');
    expect($types)->each->toBe('TextToken');
});

// ==========================================
// End-to-end: PHP comments no longer crash
// ==========================================

test('component inside PHP line comment is not compiled by Blaze', function () {
    $compiled = compile('php-comment-parent.blade.php');

    // Blaze should NOT emit ensureCompiled/require_once for the child component.
    // Before the fix, Blaze's tokenizer would parse <x-php-comment-child /> inside
    // the PHP comment and inject its own compilation calls.
    expect($compiled)->not->toContain('ensureCompiled');

    // The child component's function hash should not appear (Blaze didn't wrap it).
    // Laravel's compileComponentTags may still find it, but Blaze should not.
    expect($compiled)->not->toContain('$__blaze->pushData');
    expect($compiled)->toContain('visible');
});

// ==========================================
// Controls: Blade comments and @php still work
// ==========================================

test('component inside Blade comment is correctly ignored', function () {
    $output = blade(
        view: <<<'BLADE'
{{-- <x-mycomp /> --}}
<div>visible</div>
BLADE,
        components: [
            'mycomp' => <<<'BLADE'
@blaze
<span>SHOULD NOT APPEAR</span>
BLADE,
        ],
    );

    expect($output)->toContain('visible');
    expect($output)->not->toContain('SHOULD NOT APPEAR');
});

test('component inside @php block is correctly ignored', function () {
    $output = blade(
        view: <<<'BLADE'
@php // <x-mycomp /> @endphp
<div>visible</div>
BLADE,
        components: [
            'mycomp' => <<<'BLADE'
@blaze
<span>SHOULD NOT APPEAR</span>
BLADE,
        ],
    );

    expect($output)->toContain('visible');
    expect($output)->not->toContain('SHOULD NOT APPEAR');
});

// ==========================================
// Edge cases
// ==========================================

test('tokenizer skips component inside PHP string', function () {
    $tokenizer = new Tokenizer();

    $input = <<<'TPL'
<?php echo "<x-button />"; ?>
<x-real />
TPL;

    $tokens = $tokenizer->tokenize($input);

    // Only <x-real /> should be parsed as a component
    $selfClose = array_filter($tokens, fn($t) => $t instanceof TagSelfCloseToken);
    expect(count($selfClose))->toBe(1);
    expect(array_values($selfClose)[0]->name)->toBe('real');
});

test('tokenizer skips component inside unclosed PHP block at EOF', function () {
    $tokenizer = new Tokenizer();

    $input = '<?php // <x-button />';

    $tokens = $tokenizer->tokenize($input);

    $types = array_map(fn($t) => class_basename($t), $tokens);

    expect($types)->not->toContain('TagSelfCloseToken');
    expect($types)->each->toBe('TextToken');
});

test('tokenizer handles multiple PHP blocks with real component between them', function () {
    $tokenizer = new Tokenizer();

    $input = <<<'TPL'
<?php // <x-hidden1 /> ?>
<x-visible />
<?php /* <x-hidden2 /> */ ?>
TPL;

    $tokens = $tokenizer->tokenize($input);

    $selfClose = array_filter($tokens, fn($t) => $t instanceof TagSelfCloseToken);
    expect(count($selfClose))->toBe(1);
    expect(array_values($selfClose)[0]->name)->toBe('visible');
});

test('component inside PHP block comment renders correctly end-to-end', function () {
    $output = blade(
        view: <<<'BLADE'
<?php /* <x-mycomp /> */ ?>
<div>visible</div>
BLADE,
        components: [
            'mycomp' => <<<'BLADE'
@blaze
<span>SHOULD NOT APPEAR</span>
BLADE,
        ],
    );

    expect($output)->toContain('visible');
    expect($output)->not->toContain('SHOULD NOT APPEAR');
});
