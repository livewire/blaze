<?php

use Illuminate\Support\Facades\Artisan;
use Livewire\Blaze\BladeRenderer;
use Livewire\Blaze\BladeService;
use Livewire\Blaze\Folder\Foldable;
use Livewire\Blaze\Parser\Parser;

beforeEach(fn () => Artisan::call('view:clear'));

test('defers translations to runtime', function () {
    $input = '<x-foldable.translated />';

    $node = app(Parser::class)->parse($input)[0];
    $foldable = new Foldable($node, fixture_path('views/components/foldable/translated.blade.php'), app(BladeRenderer::class), app(BladeService::class));

    expect($foldable->fold())->toEqualCollapsingWhitespace(<<<'BLADE'
        <button aria-label="{{ app('translator')->get('Save & close') }}" data-raw="{!! app('translator')->get('Save & close') !!}">
            {{ app('translator')->get('Save & close') }}
            {{ app('translator')->get('Save & close') }}
            {{ app('translator')->choice('apples', 2) }}
            {!! app('translator')->get('Save & close') !!}
            <span>{{ e(app('translator')->get('Save & close')) }}</span>
        </button>
        BLADE
    );
});
