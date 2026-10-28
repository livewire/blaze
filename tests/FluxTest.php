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
 * Checks that attributes passed through delegate component are escaped exactly once, like in Blade
 */
test('delegate component forwards attributes like Blade', function (string $template, array $data = []) {
    Blade::anonymousComponentPath(fixture_path('views/flux'), 'flux');

    compare($template, $data);
})->with([
    'bound value with quotes and html' => ['<flux:checkbox :label="\'Label\'" :aria-label="$value" />', ['value' => 'a"b<c>&d\'e']],
    'static value with entities' => ['<flux:checkbox label="Label" aria-label="A &copy; &nbsp; &#169; &amp; B" />'],
    'forwarded attribute bag' => ['<flux:icon name="home" :attributes="$bag" />', ['bag' => new ComponentAttributeBag([
        'data-value' => e('q"><img src=x onerror=alert(1)>'),
        'data-label' => 'A &copy; B',
    ])]],
    'kebab-case prop' => ['<flux:delegated :foo-bar="$value" />', ['value' => 'a"b<c>&d\'e']],
    'kebab-case prop to non-Blaze target' => ['<flux:delegated variant="non-blaze" :foo-bar="$value" />', ['value' => 'a"b<c>&d\'e']],
    'echoed prop' => ['<flux:delegated foo-bar="Hi {{ $value }}" />', ['value' => 'a"b<c>&d\'e']],
    'echoed prop to non-Blaze target' => ['<flux:delegated variant="non-blaze" foo-bar="Hi {{ $value }}" />', ['value' => 'a"b<c>&d\'e']],
    'static prop with entities' => ['<flux:delegated foo-bar="A &amp; B" />'],
]);

/**
 * Checks that a delegate target without @blaze gets the props of its delegating component, not of the Blade component around it
 */
test('delegate component forwards props to non-Blaze target inside Blade component like Blade', function () {
    Blade::anonymousComponentPath(fixture_path('views/flux'), 'flux');

    compare('<x-delegated-wrapper foo-bar="outer" />');
});

/**
 * Checks that an attribute bag a folded component passes to @unblaze is escaped, like in Blade
 */
test('folded component escapes attributes passed to unblaze like Blade', function (string $template) {
    compare($template, ['value' => '"><img src=x onerror=alert(1)> a"b<c>&d\'e']);
})->with([
    'input error attributes' => ['<flux:input name="email" label="Email" :error:aria-label="$value" />'],
    'checkbox group error attributes' => ['<flux:checkbox.group label="Label" :error:aria-label="$value"><flux:checkbox value="a" label="A" /></flux:checkbox.group>'],
]);
