<?php

use Flux\FluxServiceProvider;
use FluxPro\FluxProServiceProvider;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\ComponentAttributeBag;
use Livewire\LivewireServiceProvider;

beforeEach(fn () => App::register(LivewireServiceProvider::class));
beforeEach(fn () => App::register(FluxServiceProvider::class));
beforeEach(fn () => Artisan::call('view:clear'));

test('input', fn () => compare(<<<'BLADE'
    <flux:input label="Name" placeholder="Your name" />
    BLADE
));

test('button', fn () => compare(<<<'BLADE'
    <flux:button type="submit" variant="primary">Save changes</flux:button>
    BLADE
));

test('button with dynamic wire:click', fn () => compare(<<<'BLADE'
    <flux:button wire:click="save({{ $id }})">Save changes</flux:button>
    BLADE, ['id' => 1]
));

test('icon', fn () => compare(<<<'BLADE'
    <flux:icon icon="loading" />
    BLADE
));

test('modal', fn () => compare(<<<'BLADE'
    <flux:modal.trigger name="edit-profile">
        <flux:button>Edit profile</flux:button>
    </flux:modal.trigger>

    <flux:modal name="edit-profile" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Update profile</flux:heading>
                <flux:text class="mt-2">Make changes to your personal details.</flux:text>
            </div>

            <flux:input label="Name" placeholder="Your name" />

            <flux:input label="Date of birth" type="date" />

            <div class="flex">
                <flux:spacer />

                <flux:button type="submit" variant="primary">Save changes</flux:button>
            </div>
        </div>
    </flux:modal>
    BLADE
));

test('select', fn () => compare(<<<'BLADE'
    <flux:select placeholder="Choose industries...">
        <flux:select.option>Photography</flux:select.option>
        <flux:select.option>Design services</flux:select.option>
        <flux:select.option>Web development</flux:select.option>
        <flux:select.option>Accounting</flux:select.option>
        <flux:select.option>Legal services</flux:select.option>
        <flux:select.option>Consulting</flux:select.option>
        <flux:select.option>Other</flux:select.option>
    </flux:select>
    BLADE
));

/**
 * Checks that bound attributes passed through delegate component aren't double escaped
 */
test('select with bound attribute with ampersand', fn () => compare(<<<'BLADE'
    <flux:select :placeholder="'Country & Region'" />
    BLADE
));

/**
 * Checks that bound attributes passed through delegate component are still escaped
 */
test('checkbox with bound attribute with quotes and html', fn () => compare(<<<'BLADE'
    <flux:checkbox :label="'Label'" :aria-label="$value" />
    BLADE,
    ['value' => 'a"b<c>&d\'e'],
));

/**
 * Checks that entities in static attributes passed through delegate component are not encoded twice
 */
test('checkbox with static attribute with entities', fn () => compare(<<<'BLADE'
    <flux:checkbox label="Label" aria-label="A &copy; &nbsp; &#169; &amp; B" />
    BLADE
));

test('select option with static attribute with entities', fn () => compare(<<<'BLADE'
    <flux:select><flux:select.option value="1" data-label="A &copy;&nbsp;B">A &amp; B</flux:select.option></flux:select>
    BLADE
));

test('checkbox with attributes bag', fn () => compare(<<<'BLADE'
    <flux:checkbox label="Label" :attributes="$bag" />
    BLADE,
    ['bag' => new ComponentAttributeBag(['data-label' => 'A &copy; B'])],
));

/**
 * Checks that kebab-case props passed through delegate component are escaped once
 */
test('delegate component with kebab-case prop', function () {
    Blade::anonymousComponentPath(fixture_path('views/flux'), 'flux');

    compare(<<<'BLADE'
        <flux:delegated foo-bar="A & B" :data-value="$value" />
        <flux:delegated :foo-bar="$value" />
        BLADE,
        ['value' => 'a"b<c>&d\'e'],
    );
});

/**
 * Checks that attribute bags forwarded into delegate components are still escaped
 */
test('delegate component with forwarded attributes bag', fn () => compare(<<<'BLADE'
    <x-icon-wrapper :data-value="$value" :class="$value" />
    BLADE,
    ['value' => 'q"><img src=x onerror=alert(1)>'],
));
