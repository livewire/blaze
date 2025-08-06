<?php

describe('excersise the compiler', function () {
    it('component with static attribute and slot', function () {
        $compiler = app('blaze')->compiler();

        $tokens = $compiler->tokenize('<x-button size="lg">Click Me</x-button>');

        $ast = $compiler->parse($tokens);

        $output = $compiler->render($ast);

        expect($output)->toBe('<x-button size="lg">Click Me</x-button>');
    });
});
