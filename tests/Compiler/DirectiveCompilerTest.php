<?php

use Livewire\Blaze\Compiler\DirectiveCompiler;

test('compiles a registered custom directive', function () {
    $result = DirectiveCompiler::make()
        ->directive('greet', fn ($expression) => "<?php echo 'Hello ' . {$expression}; ?>")
        ->compile('@greet($name)');

    expect($result)->toBe("<?php echo 'Hello ' . \$name; ?>");
});

test('ignores built-in blade directives', function ($directive) {
    $input = $directive;

    $result = DirectiveCompiler::make()->compile($input);

    expect($result)->toBe($input);
})->with([
    '@if($condition)',
    '@foreach($items as $item)',
    '@include("partial")',
    '@extends("layout")',
    '@yield("content")',
    '@section("content")',
]);

test('compiles custom directives while preserving built-in ones', function () {
    $result = DirectiveCompiler::make()
        ->directive('custom', fn ($expression) => "<?php /* custom: {$expression} */ ?>")
        ->compile('@if($condition) @custom($value) @endif');

    expect($result)->toBe('@if($condition) <?php /* custom: $value */ ?> @endif');
});

test('handles escaped directives', function () {
    $result = DirectiveCompiler::make()
        ->directive('custom', fn ($expression) => "COMPILED")
        ->compile('@@custom($value)');

    expect($result)->toBe('@custom($value)');
});

test('preserves templates with no directives', function () {
    $input = '<div class="container">Hello World</div>';

    $result = DirectiveCompiler::make()->compile($input);

    expect($result)->toBe($input);
});

test('compiles multiple custom directives', function () {
    $result = DirectiveCompiler::make()
        ->directive('foo', fn ($expr) => "[FOO:{$expr}]")
        ->directive('bar', fn ($expr) => "[BAR:{$expr}]")
        ->compile('@foo($a) @bar($b)');

    expect($result)->toBe('[FOO:$a] [BAR:$b]');
});

test('compiles directive without arguments', function () {
    $result = DirectiveCompiler::make()
        ->directive('separator', fn () => '<hr />')
        ->compile('@separator');

    expect($result)->toBe('<hr />');
});

test('preserves php blocks', function () {
    $input = '<?php echo "hello"; ?> @custom($val)';

    $result = DirectiveCompiler::make()
        ->directive('custom', fn ($expr) => "[{$expr}]")
        ->compile($input);

    expect($result)->toBe('<?php echo "hello"; ?> [$val]');
});

test('make returns a fluent instance', function () {
    $compiler = DirectiveCompiler::make();

    expect($compiler)->toBeInstanceOf(DirectiveCompiler::class);
    expect($compiler->directive('test', fn () => ''))->toBe($compiler);
});
