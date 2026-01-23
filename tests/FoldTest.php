<?php

describe('fold elligable components', function () {
    beforeEach(function () {
        app('blade.compiler')->anonymousComponentPath(__DIR__.'/fixtures/components');
    });

    function blazeCompile(string $input): string
    {
        return app('blaze')->compile($input);
    }

    it('simple component', function () {
        $input = '<x-button>Save</x-button>';
        $output = '<button type="button">Save</button>';

        expect(blazeCompile($input))->toBe($output);
    });

    it('strips double quotes from attributes with string literals', function () {
        $input = '<x-avatar :name="\'Hi\'" :src="\'there\'" />';

        expect(blazeCompile($input))->not->toContain('src=""');
        expect(blazeCompile($input))->not->toContain('alt=""');
    });

    it('falls back to function compilation when dynamic prop is used in PHP block', function () {
        // Avatar component uses $name in @php block, so it can't fold when name is dynamic
        $input = '<x-avatar :name="$foo->bar" :src="$baz->qux" />';

        // Should NOT be folded - expect function-based compilation
        expect(blazeCompile($input))->toContain('$__blaze->ensureCompiled');
        expect(blazeCompile($input))->not->toContain('src="{{ $baz->qux }}"');
    });

    it('does not fold component with dynamic props', function () {
        // With the new simplified approach, ANY dynamic prop prevents folding
        $input = '<x-button :type="$buttonType">Click</x-button>';

        // Should fall back to function compilation
        expect(blazeCompile($input))->toContain('$__blaze->ensureCompiled');
    });

    it('with static props', function () {
        $input = '<x-alert message="Success!" />';
        $output = '<div class="alert">Success!</div>';

        expect(blazeCompile($input))->toBe($output);
    });

    it('with static props containing dynamic characters like dollar signs', function () {
        $input = '<x-button wire:click="$refresh" />';
        $output = '<button type="button" wire:click="$refresh"></button>';

        expect(blazeCompile($input))->toBe($output);
    });

    it('with slot content containing dollar sign followed by numbers', function () {
        // Dollar signs followed by numbers (e.g., "$49") must not be interpreted as
        // regex backreferences when restoring slot placeholders
        $input = '<x-button>$49.00</x-button>';
        $output = '<button type="button">$49.00</button>';

        expect(blazeCompile($input))->toBe($output);
    });

    it('dynamic slot', function () {
        $input = '<x-button>{{ $name }}</x-button>';
        $output = '<button type="button">{{ $name }}</button>';

        expect(blazeCompile($input))->toBe($output);
    });

    it('dynamic attributes', function () {
        $input = '<x-button :type="$type">Save</x-button>';

        // With simplified approach, dynamic props prevent folding
        expect(blazeCompile($input))->toContain('$__blaze->ensureCompiled');
    });

    it('dynamic short attributes', function () {
        $input = '<x-button :$type>Save</x-button>';

        // With simplified approach, dynamic props prevent folding
        expect(blazeCompile($input))->toContain('$__blaze->ensureCompiled');
    });

    it('dynamic echo attributes', function () {
        $input = '<x-button type="foo {{ $type }}">Save</x-button>';

        // With simplified approach, dynamic props prevent folding
        expect(blazeCompile($input))->toContain('$__blaze->ensureCompiled');
    });

    it('folds when dynamic attribute is not a defined prop', function () {
        // The link-with-props component has @props(['label' => 'Click me'])
        // The :href attribute is NOT defined in @props, so it goes to $attributes
        // This should NOT prevent folding
        $input = '<x-link-with-props :href="$url">Go</x-link-with-props>';
        $output = blazeCompile($input);

        // Should be folded (not contain ensureCompiled)
        expect($output)->not->toContain('$__blaze->ensureCompiled');

        // Should contain the folded anchor tag
        expect($output)->toContain('<a');

        // Dynamic attributes use boolean fencing pattern (handles false/null)
        expect($output)->toContain('$__blazeAttr = $url');
        expect($output)->toContain('href="');

        // Slot content should be present
        expect($output)->toContain('Go');
    });

    it('does not fold when dynamic attribute IS a defined prop', function () {
        // The button component has @props(['type' => 'button'])
        // The :type attribute IS defined in @props, so dynamic value prevents folding
        $input = '<x-button :type="$buttonType">Click</x-button>';
        $output = blazeCompile($input);

        // Should NOT be folded
        expect($output)->toContain('$__blaze->ensureCompiled');
    });

    it('folds with safe dynamic attribute and generates conditional PHP for boolean handling', function () {
        $input = '<x-button-safe-disabled :disabled="$isDisabled">Save</x-button-safe-disabled>';
        $output = blazeCompile($input);

        // Should be folded (not contain ensureCompiled)
        expect($output)->not->toContain('$__blaze->ensureCompiled');

        // Should contain conditional PHP for the disabled attribute
        expect($output)->toContain('$__blazeAttr = $isDisabled');
        expect($output)->toContain('!== false && !is_null($__blazeAttr)');
        expect($output)->toContain('disabled="');

        // Should still contain the static type attribute
        expect($output)->toContain('type="button"');
    });

    it('handles safe dynamic attribute with true value correctly', function () {
        $input = '<x-button-safe-disabled :disabled="$isDisabled">Save</x-button-safe-disabled>';
        $output = blazeCompile($input);

        // Should generate code that outputs disabled="disabled" when true
        expect($output)->toContain("\$__blazeAttr === true ? 'disabled' : \$__blazeAttr");
    });

    it('folds static attributes without conditional PHP', function () {
        $input = '<x-button-safe-disabled disabled>Save</x-button-safe-disabled>';
        $output = blazeCompile($input);

        // Should be folded
        expect($output)->not->toContain('$__blaze->ensureCompiled');

        // Static disabled should render directly without conditional
        expect($output)->toContain('disabled="disabled"');
        expect($output)->not->toContain('$__blazeAttr');
    });

    it('dynamic slot with unfoldable component', function () {
        $input = '<x-button><x-unfoldable-button>{{ $name }}</x-unfoldable-button></x-button>';
        $output = '<button type="button"><x-unfoldable-button>{{ $name }}</x-unfoldable-button></button>';

        expect(blazeCompile($input))->toBe($output);
    });

    it('nested components', function () {
        $input = <<<'HTML'
        <x-card>
            <x-button>Edit</x-button>
            <x-button>Delete</x-button>
        </x-card>
        HTML;

        $output = <<<'HTML'
        <div class="card">
            <button type="button">Edit</button>
            <button type="button">Delete</button>
        </div>
        HTML;

        expect(blazeCompile($input))->toBe($output);
    });

    it('deeply nested components', function () {
        $input = <<<'HTML'
        <x-card>
            <x-alert>
                <x-button>Save</x-button>
            </x-alert>
        </x-card>
        HTML;

        $output = <<<'HTML'
        <div class="card">
            <div class="alert">
                <button type="button">Save</button>
            </div>
        </div>
        HTML;

        // Alert component now uses allowed pattern ($message ?? $slot)
        // All components should be folded
        expect(blazeCompile($input))->toBe($output);
    });

    it('self-closing component', function () {
        $input = '<x-alert message="Success!" />';
        $output = '<div class="alert">Success!</div>';

        expect(blazeCompile($input))->toBe($output);
    });

    it('component without @blaze is not folded', function () {
        $input = '<x-unfoldable-button>Save</x-unfoldable-button>';
        $output = '<x-unfoldable-button>Save</x-unfoldable-button>';

        expect(blazeCompile($input))->toBe($output);
    });

    it('throws exception for invalid foldable usage with $pattern', function (string $pattern, string $expectedPattern) {
        $folder = app('blaze')->folder();
        $componentNode = new \Livewire\Blaze\Nodes\ComponentNode("invalid-foldable.{$pattern}", 'x', '', [], false);

        expect(fn () => $folder->fold($componentNode))
            ->toThrow(\Livewire\Blaze\Exceptions\InvalidBlazeFoldUsageException::class);

        try {
            $folder->fold($componentNode);
        } catch (\Livewire\Blaze\Exceptions\InvalidBlazeFoldUsageException $e) {
            expect($e->getMessage())->toContain('Invalid @blaze fold usage');
            expect($e->getComponentPath())->toContain("invalid-foldable/{$pattern}.blade.php");
            expect($e->getProblematicPattern())->toBe($expectedPattern);
        }
    })->with([
        ['errors', '\\$errors'],
        ['session', 'session\\('],
        ['error', '@error\\('],
        ['csrf', '@csrf'],
        ['auth', 'auth\\(\\)'],
        ['request', 'request\\(\\)'],
        ['old', 'old\\('],
        ['once', '@once'],
    ]);

    it('named slots', function () {
        $input = '<x-modal>
    <x-slot name="header">Modal Title</x-slot>
    <x-slot name="footer">Footer Content</x-slot>
    Main content
</x-modal>';

        $output = '<div class="modal">
    <div class="modal-header">Modal Title</div>
    <div class="modal-body">Main content</div>
    <div class="modal-footer">Footer Content</div>
</div>';

        expect(blazeCompile($input))->toBe($output);
    });

    it('supports folding aware components with single word attributes', function () {
        $input = '<x-group variant="primary"><x-foldable-item /></x-group>';
        $output = '<div class="group group-primary" data-test="foo"><div class="item item-primary"></div></div>';

        expect(blazeCompile($input))->toBe($output);
    });

    it('supports folding aware components with hyphenated attributes', function () {
        $input = '<x-group variant="primary" second-variant="secondary"><x-foldable-item /></x-group>';
        $output = '<div class="group group-primary" data-test="foo" data-second-variant="secondary"><div class="item item-primary item-secondary"></div></div>';

        expect(blazeCompile($input))->toBe($output);
    });

    it('supports folding aware components with two wrapping components both with the same prop the closest one wins', function () {
        $input = '<x-group variant="primary"><x-group variant="secondary"><x-foldable-item /></x-group></x-group>';
        // The foldable-item should render the `secondary` variant because it is the closest one to the foldable-item...
        $output = '<div class="group group-primary" data-test="foo"><div class="group group-secondary" data-test="foo"><div class="item item-secondary"></div></div></div>';

        expect(blazeCompile($input))->toBe($output);
    });

    it('supports aware on unfoldable components from folded parent with single word attributes', function () {
        $input = '<x-group variant="primary"><x-item /></x-group>';

        $output = '<div class="group group-primary" data-test="foo"><div class="item item-primary"></div></div>';

        $compiled = blazeCompile($input);
        $rendered = \Illuminate\Support\Facades\Blade::render($compiled);

        expect($rendered)->toBe($output);
    });

    it('supports aware on unfoldable components from folded parent with hyphenated attributes', function () {
        $input = '<x-group variant="primary" second-variant="secondary"><x-item /></x-group>';

        $output = '<div class="group group-primary" data-test="foo" data-second-variant="secondary"><div class="item item-primary item-secondary"></div></div>';

        $compiled = blazeCompile($input);
        $rendered = \Illuminate\Support\Facades\Blade::render($compiled);

        expect($rendered)->toBe($output);
    });

    it('supports aware on unfoldable components from folded parent with dynamic attributes', function () {
        $input = '<?php $result = "bar"; ?> <x-group variant="primary" :data-test="$result"><x-item /></x-group>';

        // With simplified approach, group component won't be folded due to dynamic attribute
        // So the item component won't receive the variant through aware
        $output = '<div class="group group-primary" data-test="bar"><div class="item item-"></div></div>';

        $compiled = blazeCompile($input);
        $rendered = \Illuminate\Support\Facades\Blade::render($compiled);

        expect($rendered)->toBe($output);
    });

    it('supports verbatim blocks', function () {
        $input = <<<'BLADE'
@verbatim
<x-card>
    <x-button>Save</x-button>
</x-card>
@endverbatim
BLADE;

        $output = <<<'BLADE'
<x-card>
    <x-button>Save</x-button>
</x-card>

BLADE;

        $rendered = \Illuminate\Support\Facades\Blade::render($input);

        expect($rendered)->toBe($output);
    });

    it('can fold static props that get formatted', function () {
        $input = '<x-date date="2025-07-11 13:22:41 UTC" />';
        $output = '<div>Date is: Fri, Jul 11</div>';

        expect(blazeCompile($input))->toBe($output);
    });

    it('folds component with safe dynamic prop', function () {
        $input = '<x-modal-safe :name="$modal" title="Hello">Content</x-modal-safe>';

        // Should be folded because 'name' is in the safe list
        $compiled = blazeCompile($input);
        expect($compiled)->not->toContain('$__blaze->ensureCompiled');
        expect($compiled)->toContain('data-name="{{ $modal }}"');
        expect($compiled)->toContain('modal-title">Hello</div>');
    });

    it('folds component with multiple safe dynamic props', function () {
        $input = '<x-modal-multi-safe :name="$modal" :id="$id" title="Hello">Content</x-modal-multi-safe>';

        // Should be folded because 'name' and 'id' are both in the safe list
        $compiled = blazeCompile($input);
        expect($compiled)->not->toContain('$__blaze->ensureCompiled');
        expect($compiled)->toContain('data-name="{{ $modal }}"');
        expect($compiled)->toContain('data-id="{{ $id }}"');
    });

    it('does not fold component with unsafe dynamic prop even when safe list exists', function () {
        // 'title' is not in the safe list, so folding should be aborted
        $input = '<x-modal-safe :name="$modal" :title="$dynamicTitle">Content</x-modal-safe>';

        // Should NOT be folded - expect function-based compilation
        expect(blazeCompile($input))->toContain('$__blaze->ensureCompiled');
    });

    it('folds component with safe echo attribute syntax', function () {
        $input = '<x-modal-safe name="{{ $modal }}" title="Hello">Content</x-modal-safe>';

        // Should be folded because 'name' is in the safe list (echo syntax)
        $compiled = blazeCompile($input);
        expect($compiled)->not->toContain('$__blaze->ensureCompiled');
        expect($compiled)->toContain('data-name="{{ $modal }}"');
    });

    it('does not fold component with unsafe echo attribute', function () {
        // 'title' is not in the safe list, so folding should be aborted
        $input = '<x-modal-safe name="{{ $modal }}" title="Hello {{ $suffix }}">Content</x-modal-safe>';

        // Should NOT be folded - expect function-based compilation
        expect(blazeCompile($input))->toContain('$__blaze->ensureCompiled');
    });
});

describe('boolean attribute fencing - rendered output', function () {
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
});
