<?php

beforeEach(function () {
    app('blade.compiler')->anonymousComponentPath(__DIR__.'/fixtures/components');
});

describe('fold elligable components', function () {
    it('simple component', function () {
        $input = '<x-button>Save</x-button>';
        $output = app('blaze')->compile($input);

        expect($output)->toBe('<button type="button">Save</button>');
    });

    it('strips double quotes from attributes with string literals', function () {
        $input = '<x-avatar :name="\'Hi\'" :src="\'there\'" />';
        $output = app('blaze')->compile($input);

        expect($output)->not->toContain('src=""');
        expect($output)->not->toContain('alt=""');
    });

    it('falls back to function compilation when dynamic prop is used in PHP block', function () {
        // Avatar component uses $name in @php block, so it can't fold when name is dynamic
        $input = '<x-avatar :name="$foo->bar" :src="$baz->qux" />';
        $output = app('blaze')->compile($input);

        // Should NOT be folded - expect function-based compilation
        expect($output)->toContain('$__blaze->ensureCompiled');
        expect($output)->not->toContain('src="{{ $baz->qux }}"');
    });

    it('does not fold component with dynamic props', function () {
        // With the new simplified approach, ANY dynamic prop prevents folding
        $input = '<x-button :type="$buttonType">Click</x-button>';
        $output = app('blaze')->compile($input);

        // Should fall back to function compilation
        expect($output)->toContain('$__blaze->ensureCompiled');
    });

    it('with static props', function () {
        $input = '<x-alert message="Success!" />';
        $output = app('blaze')->compile($input);

        expect($output)->toBe('<div class="alert">Success!</div>');
    });

    it('with static props containing dynamic characters like dollar signs', function () {
        $input = '<x-button wire:click="$refresh" />';
        $output = app('blaze')->compile($input);

        expect($output)->toBe('<button type="button" wire:click="$refresh"></button>');
    });

    it('with slot content containing dollar sign followed by numbers', function () {
        // Dollar signs followed by numbers (e.g., "$49") must not be interpreted as
        // regex backreferences when restoring slot placeholders
        $input = '<x-button>$49.00</x-button>';
        $output = app('blaze')->compile($input);

        expect($output)->toBe('<button type="button">$49.00</button>');
    });

    it('dynamic slot', function () {
        $input = '<x-button>{{ $name }}</x-button>';
        $output = app('blaze')->compile($input);

        expect($output)->toBe('<button type="button">{{ $name }}</button>');
    });

    it('dynamic attributes', function () {
        $input = '<x-button :type="$type">Save</x-button>';
        $output = app('blaze')->compile($input);

        // With simplified approach, dynamic props prevent folding
        expect($output)->toContain('$__blaze->ensureCompiled');
    });

    it('dynamic short attributes', function () {
        $input = '<x-button :$type>Save</x-button>';
        $output = app('blaze')->compile($input);

        // With simplified approach, dynamic props prevent folding
        expect($output)->toContain('$__blaze->ensureCompiled');
    });

    it('dynamic echo attributes', function () {
        $input = '<x-button type="foo {{ $type }}">Save</x-button>';
        $output = app('blaze')->compile($input);

        // With simplified approach, dynamic props prevent folding
        expect($output)->toContain('$__blaze->ensureCompiled');
    });

    it('folds when dynamic attribute is not a defined prop', function () {
        // The link-with-props component has @props(['label' => 'Click me'])
        // The :href attribute is NOT defined in @props, so it goes to $attributes
        // This should NOT prevent folding
        $input = '<x-link-with-props :href="$url">Go</x-link-with-props>';
        $output = app('blaze')->compile($input);

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
        $output = app('blaze')->compile($input);

        // Should NOT be folded
        expect($output)->toContain('$__blaze->ensureCompiled');
    });

    it('folds with safe dynamic attribute and generates conditional PHP for boolean handling', function () {
        $input = '<x-button-safe-disabled :disabled="$isDisabled">Save</x-button-safe-disabled>';
        $output = app('blaze')->compile($input);

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
        $output = app('blaze')->compile($input);

        // Should generate code that outputs disabled="disabled" when true
        expect($output)->toContain("\$__blazeAttr === true ? 'disabled' : \$__blazeAttr");
    });

    it('folds static attributes without conditional PHP', function () {
        $input = '<x-button-safe-disabled disabled>Save</x-button-safe-disabled>';
        $output = app('blaze')->compile($input);

        // Should be folded
        expect($output)->not->toContain('$__blaze->ensureCompiled');

        // Static disabled should render directly without conditional
        expect($output)->toContain('disabled="disabled"');
        expect($output)->not->toContain('$__blazeAttr');
    });

    it('dynamic slot with unfoldable component', function () {
        $input = '<x-button><x-unfoldable-button>{{ $name }}</x-unfoldable-button></x-button>';
        $output = app('blaze')->compile($input);

        expect($output)->toBe('<button type="button"><x-unfoldable-button>{{ $name }}</x-unfoldable-button></button>');
    });

    it('nested components', function () {
        $input = <<<'HTML'
        <x-card>
            <x-button>Edit</x-button>
            <x-button>Delete</x-button>
        </x-card>
        HTML;

        $output = app('blaze')->compile($input);

        expect($output)->toBe(<<<'HTML'
        <div class="card">
            <button type="button">Edit</button>
            <button type="button">Delete</button>
        </div>
        HTML);
    });

    it('deeply nested components', function () {
        $input = <<<'HTML'
        <x-card>
            <x-alert>
                <x-button>Save</x-button>
            </x-alert>
        </x-card>
        HTML;

        $output = app('blaze')->compile($input);

        // Alert component now uses allowed pattern ($message ?? $slot)
        // All components should be folded
        expect($output)->toBe(<<<'HTML'
        <div class="card">
            <div class="alert">
                <button type="button">Save</button>
            </div>
        </div>
        HTML);
    });

    it('self-closing component', function () {
        $input = '<x-alert message="Success!" />';
        $output = app('blaze')->compile($input);

        expect($output)->toBe('<div class="alert">Success!</div>');
    });

    it('component without @blaze is not folded', function () {
        $input = '<x-unfoldable-button>Save</x-unfoldable-button>';
        $output = app('blaze')->compile($input);

        expect($output)->toBe('<x-unfoldable-button>Save</x-unfoldable-button>');
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

        $output = app('blaze')->compile($input);

        expect($output)->toBe('<div class="modal">
    <div class="modal-header">Modal Title</div>
    <div class="modal-body">Main content</div>
    <div class="modal-footer">Footer Content</div>
</div>');
    });

    it('supports folding aware components with single word attributes', function () {
        $input = '<x-group variant="primary"><x-foldable-item /></x-group>';
        $output = app('blaze')->compile($input);

        expect($output)->toBe('<div class="group group-primary" data-test="foo"><div class="item item-primary"></div></div>');
    });

    it('supports folding aware components with hyphenated attributes', function () {
        $input = '<x-group variant="primary" second-variant="secondary"><x-foldable-item /></x-group>';
        $output = app('blaze')->compile($input);

        expect($output)->toBe('<div class="group group-primary" data-test="foo" data-second-variant="secondary"><div class="item item-primary item-secondary"></div></div>');
    });

    it('supports folding aware components with two wrapping components both with the same prop the closest one wins', function () {
        $input = '<x-group variant="primary"><x-group variant="secondary"><x-foldable-item /></x-group></x-group>';
        $output = app('blaze')->compile($input);

        // The foldable-item should render the `secondary` variant because it is the closest one to the foldable-item...
        expect($output)->toBe('<div class="group group-primary" data-test="foo"><div class="group group-secondary" data-test="foo"><div class="item item-secondary"></div></div></div>');
    });

    it('supports aware on unfoldable components from folded parent with single word attributes', function () {
        $input = '<x-group variant="primary"><x-item /></x-group>';
        $output = app('blaze')->compile($input);
        $rendered = \Illuminate\Support\Facades\Blade::render($output);

        expect($rendered)->toBe('<div class="group group-primary" data-test="foo"><div class="item item-primary"></div></div>');
    });

    it('supports aware on unfoldable components from folded parent with hyphenated attributes', function () {
        $input = '<x-group variant="primary" second-variant="secondary"><x-item /></x-group>';
        $output = app('blaze')->compile($input);
        $rendered = \Illuminate\Support\Facades\Blade::render($output);

        expect($rendered)->toBe('<div class="group group-primary" data-test="foo" data-second-variant="secondary"><div class="item item-primary item-secondary"></div></div>');
    });

    it('supports aware on unfoldable components from folded parent with dynamic attributes', function () {
        $input = '<?php $result = "bar"; ?> <x-group variant="primary" :data-test="$result"><x-item /></x-group>';
        $output = app('blaze')->compile($input);
        $rendered = \Illuminate\Support\Facades\Blade::render($output);

        // With simplified approach, group component won't be folded due to dynamic attribute
        // So the item component won't receive the variant through aware
        expect($rendered)->toBe('<div class="group group-primary" data-test="bar"><div class="item item-"></div></div>');
    });

    it('supports verbatim blocks', function () {
        $input = <<<'BLADE'
@verbatim
<x-card>
    <x-button>Save</x-button>
</x-card>
@endverbatim
BLADE;

        $output = \Illuminate\Support\Facades\Blade::render($input);

        expect($output)->toBe(<<<'BLADE'
<x-card>
    <x-button>Save</x-button>
</x-card>

BLADE);
    });

    it('can fold static props that get formatted', function () {
        $input = '<x-date date="2025-07-11 13:22:41 UTC" />';
        $output = app('blaze')->compile($input);

        expect($output)->toBe('<div>Date is: Fri, Jul 11</div>');
    });

    it('folds component with safe dynamic prop', function () {
        $input = '<x-modal-safe :name="$modal" title="Hello">Content</x-modal-safe>';
        $output = app('blaze')->compile($input);

        // Should be folded because 'name' is in the safe list
        expect($output)->not->toContain('$__blaze->ensureCompiled');
        expect($output)->toContain('data-name="{{ $modal }}"');
        expect($output)->toContain('modal-title">Hello</div>');
    });

    it('folds component with multiple safe dynamic props', function () {
        $input = '<x-modal-multi-safe :name="$modal" :id="$id" title="Hello">Content</x-modal-multi-safe>';
        $output = app('blaze')->compile($input);

        // Should be folded because 'name' and 'id' are both in the safe list
        expect($output)->not->toContain('$__blaze->ensureCompiled');
        expect($output)->toContain('data-name="{{ $modal }}"');
        expect($output)->toContain('data-id="{{ $id }}"');
    });

    it('does not fold component with unsafe dynamic prop even when safe list exists', function () {
        // 'title' is not in the safe list, so folding should be aborted
        $input = '<x-modal-safe :name="$modal" :title="$dynamicTitle">Content</x-modal-safe>';
        $output = app('blaze')->compile($input);

        // Should NOT be folded - expect function-based compilation
        expect($output)->toContain('$__blaze->ensureCompiled');
    });

    it('folds component with safe echo attribute syntax', function () {
        $input = '<x-modal-safe name="{{ $modal }}" title="Hello">Content</x-modal-safe>';
        $output = app('blaze')->compile($input);

        // Should be folded because 'name' is in the safe list (echo syntax)
        expect($output)->not->toContain('$__blaze->ensureCompiled');
        expect($output)->toContain('data-name="{{ $modal }}"');
    });

    it('does not fold component with unsafe echo attribute', function () {
        // 'title' is not in the safe list, so folding should be aborted
        $input = '<x-modal-safe name="{{ $modal }}" title="Hello {{ $suffix }}">Content</x-modal-safe>';
        $output = app('blaze')->compile($input);

        // Should NOT be folded - expect function-based compilation
        expect($output)->toContain('$__blaze->ensureCompiled');
    });

    it('does not fold component when unsafe slot has content', function () {
        // card-unsafe-slot has unsafe: ['slot'], so any slot content should abort folding
        $input = '<x-card-unsafe-slot>Some content</x-card-unsafe-slot>';
        $output = app('blaze')->compile($input);

        // Should NOT be folded because slot has content and is in unsafe list
        expect($output)->toContain('$__blaze->ensureCompiled');
    });

    it('folds component when unsafe slot is not provided (self-closing)', function () {
        // card-unsafe-slot has unsafe: ['slot'], self-closing means no slot
        $input = '<x-card-unsafe-slot />';
        $output = app('blaze')->compile($input);

        // Should be folded because no slot is provided
        expect($output)->not->toContain('$__blaze->ensureCompiled');
        expect($output)->toContain('<div class="card">');
    });

    it('does not fold component when unsafe named slot has content', function () {
        // card-unsafe-footer has unsafe: ['footer'], so footer slot should abort folding
        $input = '<x-card-unsafe-footer>Body<x-slot:footer>Footer content</x-slot:footer></x-card-unsafe-footer>';
        $output = app('blaze')->compile($input);

        // Should NOT be folded because footer slot has content and is in unsafe list
        expect($output)->toContain('$__blaze->ensureCompiled');
    });

    it('folds component when unsafe named slot is not provided', function () {
        // card-unsafe-footer has unsafe: ['footer'], but if footer is not provided, folding is OK
        $input = '<x-card-unsafe-footer>Body only</x-card-unsafe-footer>';
        $output = app('blaze')->compile($input);

        // Should be folded because footer slot is not provided
        expect($output)->not->toContain('$__blaze->ensureCompiled');
        expect($output)->toContain('<div class="card">');
    });

    it('does not fold component when unsafe attribute is dynamic', function () {
        // button-unsafe-type has unsafe: ['type'], so dynamic type should abort folding
        // even though type is NOT in @props (it goes to $attributes)
        $input = '<x-button-unsafe-type :type="$buttonType" />';
        $output = app('blaze')->compile($input);

        // Should NOT be folded because type is dynamic and in unsafe list
        expect($output)->toContain('$__blaze->ensureCompiled');
    });

    it('folds component when unsafe attribute is static', function () {
        // button-unsafe-type has unsafe: ['type'], but static type is OK
        $input = '<x-button-unsafe-type type="submit" />';
        $output = app('blaze')->compile($input);

        // Should be folded because type is static
        expect($output)->not->toContain('$__blaze->ensureCompiled');
        expect($output)->toContain('<button');
    });

    it('folds component with safe wildcard and dynamic props', function () {
        // button-all-safe has safe: ['*'], so all dynamic values should be allowed
        $input = '<x-button-all-safe :type="$type" :label="$label" />';
        $output = app('blaze')->compile($input);

        // Should be folded because wildcard makes all dynamic values safe
        expect($output)->not->toContain('$__blaze->ensureCompiled');
        expect($output)->toContain('<button');
    });

    it('does not fold component when :$attributes spread is used', function () {
        // When :$attributes is passed, defined props could be inside that bag
        // Should abort folding to be safe
        $input = '<x-button :$attributes>Click</x-button>';
        $output = app('blaze')->compile($input);

        // Should NOT be folded because :$attributes spread is used
        expect($output)->toContain('$__blaze->ensureCompiled');
    });

    it('does not fold component when :$attributes spread is used even with safe wildcard', function () {
        // Even with safe: ['*'], :$attributes spread can't be folded because
        // the attributes bag can't be evaluated at compile time
        $input = '<x-button-all-safe :type="$type" :label="$label" :$attributes />';

        // Should NOT fold because :$attributes spread is fundamentally incompatible with folding
        expect(app('blaze')->compile($input))->toContain('$__blaze->ensureCompiled');
    });
});
