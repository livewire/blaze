<?php

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Illuminate\View\Component;
use Livewire\Blaze\Exceptions\LeftoverPlaceholdersException;

use function Livewire\invade;

beforeEach(fn () => Artisan::call('view:clear'));

dataset('strings', [
    'unsafe' => '"><img src=x onerror=alert(1)> a"b<c>&d\'e',
    'entity' => 'A &amp; B',
]);

dataset('values', [
    'unsafe' => '"><img src=x onerror=alert(1)> a"b<c>&d\'e',
    'entity' => 'A &amp; B',
    'false' => false,
    'null' => null,
    'true' => true,
    'html' => new HtmlString('<b>A &amp; B</b>'),
]);

/**
 * Check that a template fails to fold, and renders the same as Blade compiled without folding.
 */
function compareUnfolded(string $template, array $data = []): void
{
    expect(fn () => render($template, $data))->toThrow(LeftoverPlaceholdersException::class);

    invade(app('blaze'))->throw = false;
    Artisan::call('view:clear');
    Component::flushCache();

    compare($template, $data);
}

test('bound attributes', fn ($value) => compare(<<<'BLADE'
    <x-foldable.input :type="$value" :placeholder="$value" />
    BLADE, ['value' => $value]
))->with('strings');

test('bound attributes in unblaze', fn ($value) => compare(<<<'BLADE'
    <x-foldable.attributes-unblaze :title="$value" :name="$value" />
    BLADE, ['value' => $value]
))->with('values');

test('bound attributes escaped in the template', fn ($value) => compare(<<<'BLADE'
    <x-foldable.escaped-attributes :title="$value" />
    BLADE, ['value' => $value]
))->with('values');

// Blade escapes the attribute bag copy of objects like collections by marking them to be escaped
// when cast to a string, which marks the raw copy too, since it's the same object
dataset('objects', [
    'collection' => fn () => collect(['"><img src=x onerror=alert(1)>']),
]);

test('bound objects', fn ($value) => compare(<<<'BLADE'
    <x-foldable.raw-attributes :content="$value" />
    BLADE, ['value' => $value]
))->with('objects');

// merge() doesn't escape objects, so the bag casts the marked object to a string only once
test('bound objects merged as defaults', fn ($value) => compare(<<<'BLADE'
    <x-foldable.merged-attributes :title="$value" />
    BLADE, ['value' => $value]
))->with('objects');

test('bound objects in unblaze', fn ($value) => compare(<<<'BLADE'
    <x-foldable.attributes-unblaze :title="$value" :name="$value" />
    BLADE, ['value' => $value]
))->with('objects');

test('bound objects with echo handlers', function ($value) {
    Blade::stringable(Collection::class, fn () => 'handled');

    compare(<<<'BLADE'
        <x-foldable.raw-attributes :content="$value" />
        <x-foldable.raw-bag-attributes :title="$value" />
        BLADE, ['value' => $value]
    );
})->with('objects');

test('bound attributes merged into the same class attribute', fn () => compare(<<<'BLADE'
    <x-foldable.class-prop :variant="$variant" :class="$class" />
    BLADE, ['variant' => 'text-sm', 'class' => 'p-4']
));

test('aware in unblaze', fn ($value) => compare(<<<'BLADE'
    <x-wrapper :type="$value">
        <x-foldable.aware-unblaze />
    </x-wrapper>
    BLADE, ['value' => $value]
))->with('values');

// Copies of a bound attribute that were escaped again, merged or transformed before reaching
// the output or an @unblaze scope have no known runtime equivalent, so the component isn't folded
test('bound attributes used in other ways are compiled without folding', fn (string $template, mixed $value) => compareUnfolded($template, ['value' => $value]))->with([
    'passed to nested components in unblaze' => '<x-foldable.nested-attributes-unblaze :title="$value" />',
    'passed to nested slots in unblaze' => '<x-foldable.nested-slot-attributes-unblaze :title="$value" />',
    'passed to nested blade components in unblaze' => '<x-foldable.nested-blade-attributes-unblaze :title="$value" />',
    'forwarded to nested blade components in unblaze' => '<x-foldable.forwarded-blade-attributes-unblaze :disabled="$value" />',
    'merged in unblaze' => '<x-foldable.merged-attributes-unblaze :title="$value" />',
    'merged into blade attribute bags in unblaze' => '<x-foldable.merged-blade-attributes-unblaze :title="$value" />',
    'attribute bags merged into blade attribute bags in unblaze' => '<x-foldable.merged-bag-attributes-unblaze :disabled="$value" />',
    'prepended in blade attribute bags in unblaze' => '<x-foldable.prepended-blade-attributes-unblaze :data-action="$value" />',
    'escaped in the template in unblaze' => '<x-foldable.escaped-attributes-unblaze :title="$value" />',
    'from the attribute bag escaped in the template in unblaze' => '<x-foldable.escaped-bag-attributes-unblaze :name="$value" />',
    'from the attribute bag echoed in the template' => '<x-foldable.echoed-bag-attributes :title="$value" />',
])->with('values');

test('bound attributes used in other ways with strings are compiled without folding', fn (string $template, string $value) => compareUnfolded($template, ['value' => $value]))->with([
    'echoed into attributes of nested components in unblaze' => '<x-foldable.nested-echo-attributes-unblaze :title="$value" />',
    'echoed into attributes of nested slots in unblaze' => '<x-foldable.nested-echo-slot-attributes-unblaze :title="$value" />',
    'merged into itself' => '<x-foldable.class-default :class="$value" />',
    'merged into itself from the attribute bag' => '<x-foldable.class-attribute :class="$value" />',
    'merged into itself from the attribute bag in unblaze' => '<x-foldable.class-attribute-unblaze :class="$value" />',
    'concatenated in unblaze' => '<x-foldable.concatenated-attributes-unblaze :name="$value" :label="$value" />',
    'transformed in the template' => '<x-foldable.js-attributes :name="$value" />',
])->with([
    'unsafe' => '"><img src=x onerror=alert(1)> a"b<c>&d\'e',
    'entity' => 'A &amp; B',
    'arbitrary variant' => '[&>span]:hidden',
]);
