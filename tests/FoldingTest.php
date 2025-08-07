<?php

describe('fold elligable nodes', function () {
    beforeEach(function () {
        app('blade.compiler')->anonymousComponentPath(__DIR__ . '/fixtures/components');
    });

    function compile(string $input): string {
        return app('blaze')->compile($input);
    }

    it('simple component with static attributes', function () {
        $input = '<x-button size="lg" color="blue">Click Me</x-button>';
        $output = '<button type="button" class="btn btn-lg btn-blue">Click Me</button>';

        expect(compile($input))->toBe($output);
    });
});
