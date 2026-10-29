<?php

use Illuminate\Support\Facades\Artisan;
use Livewire\Blaze\Exceptions\LeftoverPlaceholdersException;

use function Livewire\invade;

beforeEach(fn () => Artisan::call('view:clear'));

dataset('strings', [
    'unsafe' => '"><img src=x onerror=alert(1)> a"b<c>&d\'e',
    'entity' => 'A &amp; B',
]);

dataset('booleans', [
    'false' => false,
    'null' => null,
    'true' => true,
]);

dataset('values', [
    'unsafe' => '"><img src=x onerror=alert(1)> a"b<c>&d\'e',
    'entity' => 'A &amp; B',
    'false' => false,
    'null' => null,
    'true' => true,
]);

test('bound attributes', fn ($value) => compare(<<<'BLADE'
    <x-foldable.input :type="$value" :placeholder="$value" />
    BLADE, ['value' => $value]
))->with('strings');

test('bound attributes in unblaze', fn ($value) => compare(<<<'BLADE'
    <x-foldable.attributes-unblaze :title="$value" :name="$value" />
    BLADE, ['value' => $value]
))->with('values');

test('bound attributes passed to nested components in unblaze', fn ($value) => compare(<<<'BLADE'
    <x-foldable.nested-attributes-unblaze :title="$value" />
    BLADE, ['value' => $value]
))->with('values');

test('bound slot attributes passed to nested components in unblaze', fn ($value) => compare(<<<'BLADE'
    <x-foldable.nested-slot-attributes-unblaze :title="$value" />
    BLADE, ['value' => $value]
))->with('values');

test('echo attributes passed to nested components in unblaze', fn ($value) => compare(<<<'BLADE'
    <x-foldable.nested-echo-attributes-unblaze :title="$value" />
    BLADE, ['value' => $value]
))->with('strings');

test('echo slot attributes passed to nested components in unblaze', fn ($value) => compare(<<<'BLADE'
    <x-foldable.nested-echo-slot-attributes-unblaze :title="$value" />
    BLADE, ['value' => $value]
))->with('strings');

test('bound attributes passed to nested blade components in unblaze', fn ($value) => compare(<<<'BLADE'
    <x-foldable.nested-blade-attributes-unblaze :title="$value" />
    BLADE, ['value' => $value]
))->with('values');

test('bound attributes forwarded to nested blade components in unblaze', fn ($value) => compare(<<<'BLADE'
    <x-foldable.forwarded-blade-attributes-unblaze :disabled="$value" />
    BLADE, ['value' => $value]
))->with('booleans');

test('merged attributes in unblaze', fn ($value) => compare(<<<'BLADE'
    <x-foldable.merged-attributes-unblaze :title="$value" />
    BLADE, ['value' => $value]
))->with('values');

test('bound attributes merged into blade attribute bags in unblaze', fn ($value) => compare(<<<'BLADE'
    <x-foldable.merged-blade-attributes-unblaze :title="$value" />
    BLADE, ['value' => $value]
))->with('values');

test('attribute bags merged into blade attribute bags in unblaze', fn ($value) => compare(<<<'BLADE'
    <x-foldable.merged-bag-attributes-unblaze :disabled="$value" />
    BLADE, ['value' => $value]
))->with('booleans');

test('bound attribute merged into itself', fn ($value) => compare(<<<'BLADE'
    <x-foldable.class-default :class="$value" />
    BLADE, ['value' => $value]
))->with('strings');

test('bound attribute merged into itself from the attribute bag', fn () => compare(<<<'BLADE'
    <x-foldable.class-attribute :class="$value" />
    BLADE, ['value' => 'text-red-500']
));

test('bound attribute merged into itself from the attribute bag in unblaze', fn () => compare(<<<'BLADE'
    <x-foldable.class-attribute-unblaze :class="$value" />
    BLADE, ['value' => 'text-red-500']
));

test('bound attributes merged into the same class attribute', fn () => compare(<<<'BLADE'
    <x-foldable.class-prop :variant="$variant" :class="$class" />
    BLADE, ['variant' => 'text-sm', 'class' => 'p-4']
));

test('bound attributes concatenated in unblaze', fn ($value) => compare(<<<'BLADE'
    <x-foldable.concatenated-attributes-unblaze :name="$value" :label="$value" />
    BLADE, ['value' => $value]
))->with('strings');

test('bound attributes escaped in the template', fn ($value) => compare(<<<'BLADE'
    <x-foldable.escaped-attributes :title="$value" />
    BLADE, ['value' => $value]
))->with('values');

test('bound attributes escaped in the template in unblaze', fn ($value) => compare(<<<'BLADE'
    <x-foldable.escaped-attributes-unblaze :title="$value" />
    BLADE, ['value' => $value]
))->with('values');

test('bound attributes from the attribute bag escaped in the template in unblaze', fn ($value) => compare(<<<'BLADE'
    <x-foldable.escaped-bag-attributes-unblaze :name="$value" />
    BLADE, ['value' => $value]
))->with('booleans');

test('bound attributes escaped without double encoding in unblaze', fn ($value) => compare(<<<'BLADE'
    <x-foldable.escaped-without-double-encoding-unblaze :title="$value" />
    BLADE, ['value' => $value]
))->with('values');

test('aware in unblaze', fn ($value) => compare(<<<'BLADE'
    <x-wrapper :type="$value">
        <x-foldable.aware-unblaze />
    </x-wrapper>
    BLADE, ['value' => $value]
))->with('values');

test('bound props transformed in the template fail the fold', fn () => expect(fn () => render(<<<'BLADE'
    <x-foldable.uppercase-attributes :name="$value" />
    BLADE, ['value' => 'a&b']
))->toThrow(LeftoverPlaceholdersException::class));

test('bound props transformed in the template are compiled without folding', function () {
    invade(app('blaze'))->throw = false;

    compare(<<<'BLADE'
        <x-foldable.uppercase-attributes :name="$value" />
        BLADE, ['value' => 'a&b']
    );
});

/**
 * Blade escapes a value from the attribute bag again whenever the component escapes it or puts it
 * in another attribute bag. Blaze escapes it once, so a bound attribute is never double encoded.
 */
test('bound attributes from the attribute bag are escaped once', function (string $template, array $attributes, string $value) {
    $output = render($template, ['value' => $value]);

    foreach ($attributes as $attribute) {
        expect($output)->toContain($attribute . '="' . e($value) . '"');
    }
})->with([
    'forwarded to nested blade components in unblaze' => ['<x-foldable.forwarded-blade-attributes-unblaze :disabled="$value" />', ['disabled']],
    'merged into blade attribute bags in unblaze' => ['<x-foldable.merged-bag-attributes-unblaze :disabled="$value" />', ['disabled']],
    'escaped in the template in unblaze' => ['<x-foldable.escaped-bag-attributes-unblaze :name="$value" />', ['data-name', 'data-label']],
    'merged into itself' => ['<x-foldable.class-attribute :class="$value" />', ['class']],
    'merged into itself in unblaze' => ['<x-foldable.class-attribute-unblaze :class="$value" />', ['class']],
])->with('strings');
