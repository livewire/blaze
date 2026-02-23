<?php

use Livewire\Blaze\Compiler\UseExtractor;

test('extracts use statements from php blocks', function () {
    $statements = [];

    $result = (new UseExtractor)->extract('<?php use App\Models\User; ?>', function ($s) use (&$statements) {
        $statements[] = $s;
    });

    expect($statements)->toBe(['use App\Models\User;'])
        ->and($result)->toBe('');
});

test('extracts multiple use statements and preserves remaining code', function () {
    $statements = [];

    $result = (new UseExtractor)->extract(
        '<?php use App\Models\User;' . "\n" . 'use App\Models\Order;' . "\n" . 'User::find(1); ?>',
        function ($s) use (&$statements) { $statements[] = $s; },
    );

    expect($statements)->toBe(['use App\Models\User;', 'use App\Models\Order;'])
        ->and($result)->toBe('<?php User::find(1); ?>');
});

test('leaves blocks without use statements unchanged', function () {
    $input = '<?php echo "hello"; ?>';

    $result = (new UseExtractor)->extract($input, function () {});

    expect($result)->toBe($input);
});

test('handles aliased and group use statements', function ($input, $expected) {
    $statements = [];

    (new UseExtractor)->extract($input, function ($s) use (&$statements) {
        $statements[] = $s;
    });

    expect($statements)->toBe($expected);
})->with([
    'alias' => ['<?php use App\Models\User as UserModel; ?>', ['use App\Models\User as UserModel;']],
    'group' => ['<?php use App\Models\{User, Order}; ?>', ['use App\Models\{User, Order};']],
]);

test('extracts use statements separated by comments', function () {
    $statements = [];

    $input = '<?php use App\Models\User;' . "\n" . '// a comment' . "\n" . 'use App\Models\Order; ?>';

    $result = (new UseExtractor)->extract($input, function ($s) use (&$statements) {
        $statements[] = $s;
    });

    expect($statements)->toBe(['use App\Models\User;', 'use App\Models\Order;'])
        ->and($result)->toBe('');
});

test('preserves content around php blocks', function () {
    $statements = [];

    $result = (new UseExtractor)->extract(
        '<div><?php use App\Models\User; ?></div>',
        function ($s) use (&$statements) { $statements[] = $s; },
    );

    expect($statements)->toBe(['use App\Models\User;'])
        ->and($result)->toBe('<div></div>');
});
