<?php

use Illuminate\Support\Facades\Artisan;
use Livewire\Blaze\Blaze;
use Livewire\Blaze\BlazeManager;

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

test('blaze variables are excluded from volt fragment component arguments', function () {
    // Volt compiles @volt fragments into a Livewire mount call that captures the
    // entire template scope via get_defined_vars(). Blaze's runtime and call-site
    // temporaries must be filtered out of that capture, otherwise they end up
    // serialized into the Livewire snapshot and corrupt its checksum...
    $input = '@livewire("volt-anonymous-fragment-abc123", Livewire\Volt\Precompilers\ExtractFragments::componentArguments([...get_defined_vars(), ...array()]))';

    $compiled = app('blade.compiler')->compileString($input);

    expect($compiled)->toContain('ExtractFragments::componentArguments([...\Livewire\Blaze\Support\Utils::exceptBlazeVariables(get_defined_vars()), ...array()])');
});

test('get_defined_vars is left untouched outside volt fragment component arguments', function () {
    $input = '<?php $vars = get_defined_vars(); ?>';

    expect(Blaze::excludeBlazeVariablesFromVoltFragments($input))->toBe($input);
});
