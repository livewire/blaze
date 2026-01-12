<?php

describe('fold elligable components', function () {
    beforeEach(function () {
        app('blade.compiler')->anonymousComponentPath(__DIR__ . '/fixtures/components');
    });

    function blazeCompile(string $input): string {
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

    it('strips double quotes from complex dynamic attributes', function () {
        $input = '<x-avatar :name="$foo->bar" :src="$baz->qux" />';

        expect(blazeCompile($input))->toContain('src="{{ $baz->qux }}" alt="{{ $foo->bar }}"');
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

    it('dynamic slot', function () {
        $input = '<x-button>{{ $name }}</x-button>';
        $output = '<button type="button">{{ $name }}</button>';

        expect(blazeCompile($input))->toBe($output);
    });

    it('dynamic attributes', function () {
        $input = '<x-button :type="$type">Save</x-button>';
        $output = '<button type="{{ $type }}">Save</button>';

        expect(blazeCompile($input))->toBe($output);
    });

    it('dynamic short attributes', function () {
        $input = '<x-button :$type>Save</x-button>';
        $output = '<button type="{{ $type }}">Save</button>';

        expect(blazeCompile($input))->toBe($output);
    });

    it('dynamic echo attributes', function () {
        $input = '<x-button type="foo {{ $type }}">Save</x-button>';
        $output = '<button type="foo {{ $type }}">Save</button>';

        expect(blazeCompile($input))->toBe($output);
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

        $output = <<<HTML
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

        $output = <<<HTML
        <div class="card">
            <div class="alert">
                <button type="button">Save</button>
            </div>
        </div>
        HTML;

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

        expect(fn() => $folder->fold($componentNode))
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

        $output = '<div class="group group-primary" data-test="bar"><div class="item item-primary"></div></div>';

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
});
