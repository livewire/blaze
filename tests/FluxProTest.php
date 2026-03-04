<?php

use Flux\FluxServiceProvider;
use FluxPro\FluxProServiceProvider;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Livewire\LivewireServiceProvider;

beforeEach(fn () => App::register(LivewireServiceProvider::class));
beforeEach(fn () => App::register(FluxServiceProvider::class));
beforeEach(fn () => App::register(FluxProServiceProvider::class));
beforeEach(fn () => Artisan::call('view:clear'));

test('avatar', fn () => compare(<<<'BLADE'
    <flux:avatar src="https://unavatar.io/x/calebporzio" />
    BLADE
));

test('listbox', fn () => compare(<<<'BLADE'
    <flux:select variant="listbox" multiple placeholder="Choose industries...">
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

test('chart', fn () => compare(<<<'BLADE'
    <flux:chart wire:model="data" class="w-full aspect-2/1">
        <flux:chart.viewport class="size-full">
            <flux:chart.svg>
                <flux:chart.bar field="revenue" class="text-blue-500" radius="2 0" />
                <flux:chart.axis axis="x" field="date" position="bottom">
                    <flux:chart.axis.tick />
                    <flux:chart.axis.line />
                </flux:chart.axis>
                <flux:chart.axis axis="y" position="left">
                    <flux:chart.axis.grid />
                    <flux:chart.axis.tick />
                </flux:chart.axis>
                <flux:chart.cursor class="text-zinc-800" type="area" stroke-dasharray="4 4" />
            </flux:chart.svg>
            <flux:chart.tooltip>
                <flux:chart.tooltip.heading field="date" :format="['month' => 'long', 'day' => 'numeric']" />
                <flux:chart.tooltip.value field="revenue" label="Revenue">
                    <flux:chart.legend.indicator class="bg-blue-500" />
                </flux:chart.tooltip.value>
            </flux:chart.tooltip>
        </flux:chart.viewport>
    </flux:chart>
    BLADE
));

test('accordion', fn () => compare(<<<'BLADE'
    <flux:accordion>
        <flux:accordion.item>
            <flux:accordion.heading>What's your refund policy?</flux:accordion.heading>

            <flux:accordion.content>
                We offer a 30-day money-back guarantee.
            </flux:accordion.content>
        </flux:accordion.item>

        <flux:accordion.item>
            <flux:accordion.heading>Do you offer any discounts for bulk purchases?</flux:accordion.heading>

            <flux:accordion.content>
                Yes, we offer special discounts for bulk orders.
            </flux:accordion.content>
        </flux:accordion.item>

        <flux:accordion.item>
            <flux:accordion.heading>How do I track my order?</flux:accordion.heading>

            <flux:accordion.content>
                Once your order is shipped, you will receive an email.
            </flux:accordion.content>
        </flux:accordion.item>
    </flux:accordion>
    BLADE
));

test('context', fn () => compare(<<<'BLADE'
    <flux:context>
        <flux:card class="border-dashed border-2 px-16">
            <flux:text>Right click</flux:text>
        </flux:card>

        <flux:menu>
            <flux:menu.item icon="plus">New post</flux:menu.item>

            <flux:menu.separator />

            <flux:menu.submenu heading="Sort by">
                <flux:menu.radio.group>
                    <flux:menu.radio checked>Name</flux:menu.radio>
                    <flux:menu.radio>Date</flux:menu.radio>
                    <flux:menu.radio>Popularity</flux:menu.radio>
                </flux:menu.radio.group>
            </flux:menu.submenu>

            <flux:menu.submenu heading="Filter">
                <flux:menu.checkbox checked>Draft</flux:menu.checkbox>
                <flux:menu.checkbox checked>Published</flux:menu.checkbox>
                <flux:menu.checkbox>Archived</flux:menu.checkbox>
            </flux:menu.submenu>

            <flux:menu.separator />

            <flux:menu.item variant="danger" icon="trash">Delete</flux:menu.item>
        </flux:menu>
    </flux:context>
    BLADE
));