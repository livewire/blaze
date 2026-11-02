<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Livewire\Blaze\Exceptions\LeftoverPlaceholdersException;

beforeEach(fn () => Artisan::call('view:clear'));

test('unblaze scope holds bound attributes like blade', fn () => compare(<<<'BLADE'
    <x-foldable.scope-unblaze :title="$value" :label="$value" />
    BLADE, ['value' => '<b>A & B</b>']
));

test('unblaze scope holds bound boolean attributes like blade', fn () => compare(<<<'BLADE'
    <x-foldable.scope-unblaze :title="$true" :label="$false" />
    BLADE, ['true' => true, 'false' => false]
));

test('unblaze scope holds echo attributes like blade', fn () => compare(<<<'BLADE'
    <x-foldable.scope-unblaze title="Hi {{ $value }}" label="Hi {{ $value }}" />
    BLADE, ['value' => '<b>A & B</b>']
));

test('values transformed in unblaze scope fail the fold', function () {
    $input = '<x-foldable.transformed-scope-unblaze :name="$value" />';
    $data = ['value' => 'email'];

    expect(fn () => Blade::render($input, $data))->toThrow(LeftoverPlaceholdersException::class);

    // Without Blaze::throw(), the component is compiled without folding
    (fn () => $this->throw = false)->call(app('blaze'));

    compare($input, $data);
});
