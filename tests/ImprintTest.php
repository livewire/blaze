<?php

describe('imprint components', function () {
    beforeEach(function () {
        app('blade.compiler')->anonymousComponentPath(__DIR__ . '/fixtures/components');

        \Illuminate\Support\Facades\Artisan::call('view:clear');
    });

    it('can imprint components with nested components that use the error bag', function () {
        $input = '<x-field wire:model="search">Search</x-field>';
        $output = <<<'HTML'
        <div>
            <div>Search</div>

            <x-error name="search" />
        </div>
        HTML;

        expect(app('blaze')->compile($input))->toContain('<x-error :name="\'search\'" />');
    });
});
