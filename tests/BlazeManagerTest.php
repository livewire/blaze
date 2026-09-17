<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Livewire\Blaze\Blaze;
use Livewire\Blaze\BlazeManager;
use Livewire\Blaze\Support\Utils;

beforeEach(fn () => Artisan::call('view:clear'));

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

test('viewContainsExpiredFrontMatter returns true when folded component source is updated', function () {
    $component = fixture_path('views/components/foldable/input.blade.php');
    $modified = filemtime($component);
    $manager = app(BlazeManager::class);
    $view = view('blaze');

    $view->render();
    $manager->flushState();

    touch($component, $modified + 10);

    expect($manager->viewContainsExpiredFrontMatter($view))->toBeTrue();

    touch($component, $modified);
});

test('viewContainsExpiredFrontMatter returns false when view isnt compiled', function () {
    $manager = app(BlazeManager::class);
    $view = view('blaze');

    expect($manager->viewContainsExpiredFrontMatter($view))->toBeFalse();
});

test('registerPrefix makes a custom tag prefix compilable', function () {
    Blade::anonymousComponentNamespace('components', 'tk');

    Blaze::registerPrefix('tk:', namespace: 'tk::');

    $path = fixture_path('views/components/card.blade.php');
    $hash = Utils::hash($path);

    $output = Blaze::compileForFolding('<tk:card class="mt-8" />');

    expect($output)->toContain("_{$hash}(");
});
