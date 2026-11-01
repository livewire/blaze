<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Livewire\Blaze\Exceptions\LeftoverPlaceholdersException;

beforeEach(fn () => Artisan::call('view:clear'));

test('bound attributes passed to unblaze through the attribute bag', fn () => compare(<<<'BLADE'
    <x-foldable.attributes-unblaze :aria-label="$unsafe" :title="$entity" />
    BLADE, ['unsafe' => '"><img src=x onerror=alert(1)>', 'entity' => 'A &amp; B']
));

test('bound boolean attributes passed to unblaze through the attribute bag', fn () => compare(<<<'BLADE'
    <x-foldable.attributes-unblaze :disabled="$false" :required="$true" :hidden="$null" />
    BLADE, ['false' => false, 'true' => true, 'null' => null]
));

test('echo attributes passed to unblaze through the attribute bag', fn () => compare(<<<'BLADE'
    <x-foldable.attributes-unblaze aria-label="Hi {{ $unsafe }}" />
    BLADE, ['unsafe' => '"><img src=x onerror=alert(1)>']
));

test('bound attributes passed to unblaze as props', fn () => compare(<<<'BLADE'
    <x-foldable.props-unblaze :title="$value" />
    BLADE, ['value' => '"><img src=x onerror=alert(1)> A &amp; B']
));

test('bound attributes forwarded to unblaze of nested components', fn () => compare(<<<'BLADE'
    <x-foldable.forwarded-attributes-unblaze :title="$value" />
    BLADE, ['value' => '"><img src=x onerror=alert(1)> A &amp; B']
));

test('bound attributes escaped again in unblaze fail the fold', function () {
    expect(fn () => Blade::render('<x-foldable.escaped-attributes-unblaze :title="$value" />', ['value' => 'A & B']))
        ->toThrow(LeftoverPlaceholdersException::class);
});

test('bound attributes concatenated in unblaze fail the fold', function () {
    expect(fn () => Blade::render('<x-foldable.concatenated-attributes-unblaze :name="$value" />', ['value' => 'email']))
        ->toThrow(LeftoverPlaceholdersException::class);
});

test('bound attributes merged with an escaped copy fail the fold', function () {
    expect(fn () => Blade::render('<x-foldable.merged-class :class="$value" />', ['value' => 'text-red-500']))
        ->toThrow(LeftoverPlaceholdersException::class);
});
