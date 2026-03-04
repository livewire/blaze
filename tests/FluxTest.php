<?php

use Flux\FluxServiceProvider;
use FluxPro\FluxProServiceProvider;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
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