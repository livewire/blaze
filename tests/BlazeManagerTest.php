<?php

use Livewire\Blaze\Blaze;

test('compile preserves php directives', function () {
    $input = '@php /* uncompiled */ @endphp';

    expect(Blaze::compile($input))->toBe($input);
});

test('compileForDebug preserves php directives', function () {
    $input = '@php /* uncompiled */ @endphp';

    expect(Blaze::compileForDebug($input))->toBe($input);
});

test('compileForFolding preserves php directives', function () {
    $input = '@php /* uncompiled */ @endphp';

    expect(Blaze::compileForFolding($input))->toBe($input);
});

test('compileForUnblaze does not restore raw blocks', function () {
    $input = '@php /* uncompiled */ @endphp';

    // compileForUnblaze should only store raw blocks, not restore them.
    // They will be restored in the parent compile() method.
    expect(Blaze::compileForUnblaze($input))->toBe('@__raw_block_0__@');
});