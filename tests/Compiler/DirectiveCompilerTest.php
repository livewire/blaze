<?php

use Livewire\Blaze\Compiler\DirectiveCompiler;

test('compiles custom directives while preserving built-in ones', function () {
    $input = '@if($condition) @custom($value) @endcustom @endif';

    $result = DirectiveCompiler::make()
        ->directive('custom', fn ($expression) => "<?php /* custom: {$expression} */ ?>")
        ->directive('endcustom', fn () => "<?php /* endcustom */ ?>")
        ->compile($input);

    expect($result)->toBe('@if($condition) <?php /* custom: $value */ ?> <?php /* endcustom */ ?> @endif');
});

test('ignores escaped directives', function () {
    $input = '@@custom($value)';
    
    $result = DirectiveCompiler::make()
        ->directive('custom', fn () => '')
        ->compile($input);

    expect($result)->toBe($input);
});

test('ignores php blocks', function () {
    $input = '<?php // @custom ?>';

    $result = DirectiveCompiler::make()
        ->directive('custom', fn () => '')
        ->compile($input);

    expect($result)->toBe($input);
});