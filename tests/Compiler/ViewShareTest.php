<?php

use Illuminate\Support\Facades\View;

test('it supports View::share variables', function () {
    View::share('sharedVariable', 'shared value');

    $view = <<<'BLADE'
@blaze
<div>{{ $sharedVariable }}</div>
BLADE;

    $output = blade(
        view: '<x-test-share />',
        components: [
            'test-share' => $view,
        ]
    );

    expect(trim($output))->toBe('<div>shared value</div>');
});
