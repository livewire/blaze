<?php

use Livewire\Blaze\Compiler\UseExtractor;

test('extracts use statements from php blocks', function () {
    $input = "<?php use App\Models\User;\nuse App\Models\Order;\nUser::find(1); ?>";

    $statements = [];
    $result = (new UseExtractor)->extract($input, function ($s) use (&$statements) { $statements[] = $s; });

    expect($result)->toBe('<?php User::find(1); ?>');
    expect($statements)->toBe(['use App\Models\User;', 'use App\Models\Order;']);
});

test('handles different syntaxes', function ($input, $expected) {
    $statements = [];
    $result = (new UseExtractor)->extract($input, function ($s) use (&$statements) { $statements[] = $s; });

    expect($result)->toBe('');
    expect($statements)->toBe($expected);
})->with([
    'alias' => ['<?php use App\Models\User as UserModel; ?>', ['use App\Models\User as UserModel;']],
    'group' => ['<?php use App\Models\{User, Order}; ?>', ['use App\Models\{User, Order};']],
    'one line' => ['<?php use App\Models\User; use App\Models\Order; ?>', ['use App\Models\User;', 'use App\Models\Order;']],
]);

test('extracts use statements separated by comments', function () {
    $input = '<?php use App\Models\User;' . "\n" . '// a comment' . "\n" . 'use App\Models\Order; ?>';

    $statements = [];
    $result = (new UseExtractor)->extract($input, function ($s) use (&$statements) { $statements[] = $s; });

    expect($result)->toBe('');
    expect($statements)->toBe(['use App\Models\User;', 'use App\Models\Order;']);
});

test('leaves blocks without use statements unchanged', function () {
    $input = '<?php echo "hello"; ?>';

    $result = (new UseExtractor)->extract($input, function () {});

    expect($result)->toBe($input);
});

test('preserves content around php blocks', function () {
    $input = '<div><?php use App\Models\User; ?></div>';

    $statements = [];
    $result = (new UseExtractor)->extract($input, function ($s) use (&$statements) { $statements[] = $s; });

    expect($statements)->toBe(['use App\Models\User;'])
        ->and($result)->toBe('<div></div>');
});

test('extracts use statements from @php blocks', function () {
    $input = "@php use App\Models\User;\nuse App\Models\Order;\nUser::find(1); @endphp";

    $statements = [];
    $result = (new UseExtractor)->extract($input, function ($s) use (&$statements) { $statements[] = $s; });

    expect($result)->toBe('@php User::find(1); @endphp');
    expect($statements)->toBe(['use App\Models\User;', 'use App\Models\Order;']);
});

test('removes @php blocks containing only use statements', function () {
    $input = '@php use App\Models\User; @endphp';

    $statements = [];
    $result = (new UseExtractor)->extract($input, function ($s) use (&$statements) { $statements[] = $s; });

    expect($result)->toBe('');
    expect($statements)->toBe(['use App\Models\User;']);
});

test('leaves @php blocks without use statements unchanged', function () {
    $input = '@php echo "hello"; @endphp';

    $result = (new UseExtractor)->extract($input, function () {});

    expect($result)->toBe($input);
});

test('ignores escaped php blocks', function () {
    $input = '@@php use App\Models\User; @endphp';

    $result = (new UseExtractor)->extract($input, function () {});

    expect($result)->toBe($input);
});