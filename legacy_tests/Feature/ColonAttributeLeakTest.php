<?php

/**
 * Tests verifying that colon-prefixed attributes (icon:trailing, logo:dark, etc.)
 * behave the same in Blaze as they do in native Laravel Blade.
 *
 * In native Blade, the Flux pattern works:
 *   @php $iconTrailing ??= $attributes->pluck('icon:trailing'); @endphp
 *   @props(['iconTrailing' => null])
 *   <button {{ $attributes }}>
 *
 * pluck() removes the key from $attributes in-place, so {{ $attributes }}
 * never renders it. In Blaze, @props creates a NEW $attributes bag from
 * $__data, and if pluck's removal isn't reflected in $__data, the attribute
 * leaks back into the output.
 */

beforeEach(function () {
    app('blade.compiler')->anonymousComponentPath(__DIR__ . '/fixtures');

    \Illuminate\View\ComponentAttributeBag::macro('pluck', function ($key, $default = null) {
        $result = $this->get($key);
        unset($this->attributes[$key]);
        return $result ?? $default;
    });
});

describe('Flux pluck pattern: colon attributes must not leak (Laravel parity)', function () {
    it('plucked icon:trailing does not leak when $attributes is rendered', function () {
        $result = blade(
            components: [
                'button' => <<<'BLADE'
                    @blaze
                    @php $iconTrailing ??= $attributes->pluck('icon:trailing'); @endphp
                    @props(['iconTrailing' => null])
                    <button {{ $attributes }} data-icon="{{ $iconTrailing }}">Click</button>
                    BLADE
                ,
            ],
            view: '<x-button icon:trailing="arrow" class="btn" />',
        );

        expect($result)->toContain('data-icon="arrow"');
        expect($result)->toContain('class="btn"');
        expect($result)->not->toContain('icon:trailing');
    });

    it('plucked logo:dark and logo:light do not leak', function () {
        $result = blade(
            components: [
                'navbar' => <<<'BLADE'
                    @blaze
                    @php $logoDark ??= $attributes->pluck('logo:dark'); @endphp
                    @php $logoLight ??= $attributes->pluck('logo:light'); @endphp
                    @props(['logoDark' => null, 'logoLight' => null])
                    <nav {{ $attributes }}>
                        <img data-dark="{{ $logoDark }}" data-light="{{ $logoLight }}" />
                    </nav>
                    BLADE
                ,
            ],
            view: '<x-navbar logo:dark="dark.png" logo:light="light.png" class="nav" />',
        );

        expect($result)->toContain('data-dark="dark.png"');
        expect($result)->toContain('data-light="light.png"');
        expect($result)->toContain('class="nav"');
        expect($result)->not->toContain('logo:dark');
        expect($result)->not->toContain('logo:light');
    });

    it('plucked mask:dynamic does not leak', function () {
        $result = blade(
            components: [
                'input' => <<<'BLADE'
                    @blaze
                    @php $maskDynamic ??= $attributes->pluck('mask:dynamic'); @endphp
                    @props(['maskDynamic' => null])
                    <input {{ $attributes }} data-mask="{{ $maskDynamic }}" />
                    BLADE
                ,
            ],
            view: '<x-input mask:dynamic="999-999" class="field" />',
        );

        expect($result)->toContain('data-mask="999-999"');
        expect($result)->toContain('class="field"');
        expect($result)->not->toContain('mask:dynamic');
    });

    it('plucked badge:color does not leak', function () {
        $result = blade(
            components: [
                'navbar' => <<<'BLADE'
                    @blaze
                    @php $badgeColor ??= $attributes->pluck('badge:color'); @endphp
                    @props(['badgeColor' => 'gray'])
                    <nav {{ $attributes }}><span class="badge-{{ $badgeColor }}">1</span></nav>
                    BLADE
                ,
            ],
            view: '<x-navbar badge:color="lime" class="nav" />',
        );

        expect($result)->toContain('badge-lime');
        expect($result)->toContain('class="nav"');
        expect($result)->not->toContain('badge:color');
    });

    it('multiple plucked colon attributes do not leak', function () {
        $result = blade(
            components: [
                'button' => <<<'BLADE'
                    @blaze
                    @php $iconTrailing ??= $attributes->pluck('icon:trailing'); @endphp
                    @php $iconVariant ??= $attributes->pluck('icon:variant'); @endphp
                    @props(['iconTrailing' => null, 'iconVariant' => 'outline'])
                    <button {{ $attributes }} data-trailing="{{ $iconTrailing }}" data-variant="{{ $iconVariant }}">Click</button>
                    BLADE
                ,
            ],
            view: '<x-button icon:trailing="chevron-down" icon:variant="solid" class="btn" />',
        );

        expect($result)->toContain('data-trailing="chevron-down"');
        expect($result)->toContain('data-variant="solid"');
        expect($result)->toContain('class="btn"');
        expect($result)->not->toContain('icon:trailing');
        expect($result)->not->toContain('icon:variant');
    });

    it('framework colon attributes (wire:, x-on:) are preserved', function () {
        $result = blade(
            components: [
                'input' => <<<'BLADE'
                    @blaze
                    @php $maskDynamic ??= $attributes->pluck('mask:dynamic'); @endphp
                    @props(['maskDynamic' => null])
                    <input {{ $attributes }} />
                    BLADE
                ,
            ],
            view: '<x-input wire:model="name" x-on:click="handler()" mask:dynamic="999" />',
        );

        expect($result)->toContain('wire:model="name"');
        expect($result)->toContain('x-on:click="handler()"');
        expect($result)->not->toContain('mask:dynamic');
    });

    it('pluck without @props also prevents leak', function () {
        // Some Flux components pluck but don't declare the prop in @props
        $result = blade(
            components: [
                'button' => <<<'BLADE'
                    @blaze
                    @php $iconTrailing ??= $attributes->pluck('icon:trailing'); @endphp
                    <button {{ $attributes }} data-icon="{{ $iconTrailing }}">Click</button>
                    BLADE
                ,
            ],
            view: '<x-button icon:trailing="arrow" class="btn" />',
        );

        expect($result)->toContain('data-icon="arrow"');
        expect($result)->toContain('class="btn"');
        expect($result)->not->toContain('icon:trailing');
    });

    it('existing PropsTest pluck scenario does not leak (regression)', function () {
        // This is the existing test from PropsTest.php:781 with the
        // critical no-leak assertion added.
        $result = blade(
            components: [
                'tooltip' => <<<'BLADE'
                    @blaze
                    @php $position ??= $attributes->pluck('tooltip:position'); @endphp
                    @props(['position' => 'top'])
                    <div {{ $attributes }} data-position="{{ $position }}">Content</div>
                    BLADE
                ,
            ],
            view: '<x-tooltip tooltip:position="bottom" class="test" />',
        );

        expect($result)->toContain('data-position="bottom"');
        expect($result)->toContain('class="test"');
        expect($result)->not->toContain('tooltip:position');
    });
});
