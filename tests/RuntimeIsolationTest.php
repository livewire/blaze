<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Artisan;

beforeEach(fn () => Artisan::call('view:clear'));
beforeEach(fn () => app('blade.compiler')->anonymousComponentPath(__DIR__ . '/fixtures/components'));

it('compiles component that was previously folded', function () {
    // This verifies that when a component is compiled during folding, the runtime cache is not polluted.
    // This is important because in BladeService::isolatedRender, we use a temporary cache directory.
    // When we compile a component during folding, the compiled view is not going to be stored
    // in the regular cache directory. If the runtime cache was be polluted, the next time
    // the component is actually rendered, it would skip compilation and the rendering
    // would fail with an error saying that the cached view does not exist.
    Blade::render('<x-button>Folded button</x-button>');
    Blade::render('<x-button :type="$type">Direct button</x-button>', ['type' => 'submit']);
})->expectNotToPerformAssertions();

it('renders component that was previously compiled during folding', function () {
    // This verifies that when a component is compiled during folding and then rendered without folding,
    // its children will render correctly. This can be an issue because the component function 
    // will only be defined once per request. We used to have __DIR__ in the compiled view,
    // which caused an issue because when the component is rendered outside folding,
    // the __DIR__ pointed to the temporary Blaze directory used during folding,
    // but that directory was deleted by the time the component is rendered.
    Blade::render('<x-foldable-wrapper-with-compiled-button />');
    Blade::render('<x-foldable-wrapper-with-compiled-button :fold="false" />');
})->expectNotToPerformAssertions();