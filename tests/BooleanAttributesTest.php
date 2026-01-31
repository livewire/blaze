<?php

beforeEach(function () {
    app('blade.compiler')->anonymousComponentPath(__DIR__.'/Feature/fixtures');
});

it('omits attribute when value is false', function () {
    $result = blade(
        components: [
            'button' => <<<'BLADE'
                @blaze(fold: true, safe: ['disabled'])
                @props(['type' => 'button'])
                <button {{ $attributes->merge(['type' => $type]) }}>Click</button>
                BLADE
            ,
        ],
        view: '<x-button :disabled="$isDisabled">Save</x-button>',
        data: ['isDisabled' => false],
    );

    expect($result)->toContain('type="button"');
    expect($result)->not->toContain('disabled');
});

it('renders attribute with key as value when value is true', function () {
    $result = blade(
        components: [
            'button' => <<<'BLADE'
                @blaze(fold: true, safe: ['disabled'])
                @props(['type' => 'button'])
                <button {{ $attributes->merge(['type' => $type]) }}>Click</button>
                BLADE
            ,
        ],
        view: '<x-button :disabled="$isDisabled">Save</x-button>',
        data: ['isDisabled' => true],
    );

    expect($result)->toContain('disabled="disabled"');
});

it('omits attribute when value is null', function () {
    $result = blade(
        components: [
            'button' => <<<'BLADE'
                @blaze(fold: true, safe: ['disabled'])
                @props(['type' => 'button'])
                <button {{ $attributes->merge(['type' => $type]) }}>Click</button>
                BLADE
            ,
        ],
        view: '<x-button :disabled="$isDisabled">Save</x-button>',
        data: ['isDisabled' => null],
    );

    expect($result)->toContain('type="button"');
    expect($result)->not->toContain('disabled');
});

it('renders attribute with string value when value is a string', function () {
    $result = blade(
        components: [
            'button' => <<<'BLADE'
                @blaze(fold: true, safe: ['disabled'])
                @props(['type' => 'button'])
                <button {{ $attributes->merge(['type' => $type]) }}>Click</button>
                BLADE
            ,
        ],
        view: '<x-button :disabled="$isDisabled">Save</x-button>',
        data: ['isDisabled' => 'until-loaded'],
    );

    expect($result)->toContain('disabled="until-loaded"');
});

it('renders x-data with empty string when value is true', function () {
    $result = blade(
        components: [
            'dropdown' => <<<'BLADE'
                @blaze(fold: true, safe: ['x-data'])
                <div {{ $attributes }}>Dropdown</div>
                BLADE
            ,
        ],
        view: '<x-dropdown :x-data="$alpine">Content</x-dropdown>',
        data: ['alpine' => true],
    );

    // x-data should render as x-data="" when true (Alpine.js convention)
    expect($result)->toContain('x-data=""');
});

it('renders wire:loading with empty string when value is true', function () {
    $result = blade(
        components: [
            'spinner' => <<<'BLADE'
                @blaze(fold: true, safe: ['wire:loading'])
                <div {{ $attributes }}>Loading...</div>
                BLADE
            ,
        ],
        view: '<x-spinner :wire:loading="$show">Loading</x-spinner>',
        data: ['show' => true],
    );

    // wire:* attributes should render as wire:loading="" when true (Livewire convention)
    expect($result)->toContain('wire:loading=""');
});

it('renders static boolean attribute correctly', function () {
    $result = blade(
        components: [
            'button' => <<<'BLADE'
                @blaze(fold: true, safe: ['disabled'])
                @props(['type' => 'button'])
                <button {{ $attributes->merge(['type' => $type]) }}>Click</button>
                BLADE
            ,
        ],
        view: '<x-button disabled>Save</x-button>',
    );

    // Static disabled attribute should render as disabled="disabled"
    expect($result)->toContain('disabled="disabled"');
});

it('preserves other attributes when boolean attribute is false', function () {
    $result = blade(
        components: [
            'button' => <<<'BLADE'
                @blaze(fold: true, safe: ['disabled'])
                @props(['type' => 'button'])
                <button {{ $attributes->merge(['type' => $type]) }}>Click</button>
                BLADE
            ,
        ],
        view: '<x-button :disabled="$isDisabled" class="btn-primary">Save</x-button>',
        data: ['isDisabled' => false],
    );

    expect($result)->toContain('class="btn-primary"');
    expect($result)->toContain('type="button"');
    expect($result)->not->toContain('disabled');
});

it('handles multiple dynamic boolean attributes', function () {
    $result = blade(
        components: [
            'input' => <<<'BLADE'
                @blaze(fold: true, safe: ['disabled', 'readonly', 'required'])
                <input {{ $attributes }} />
                BLADE
            ,
        ],
        view: '<x-input :disabled="$d" :readonly="$r" :required="$req" />',
        data: ['d' => true, 'r' => false, 'req' => true],
    );

    expect($result)->toContain('disabled="disabled"');
    expect($result)->not->toContain('readonly');
    expect($result)->toContain('required="required"');
});