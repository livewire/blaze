<?php

describe('fold elligable components', function () {
    beforeEach(function () {
        app('blade.compiler')->anonymousComponentPath(__DIR__ . '/fixtures/components');
    });

    function compile(string $input): string {
        return app('blaze')->compile($input);
    }

    it('simple component', function () {
        $input = '<x-button>Save</x-button>';
        $output = '<button type="button">Save</button>';

        expect(compile($input))->toBe($output);
    });

    it('with static props', function () {
        $input = '<x-alert message="Success!" />';
        $output = '<div class="alert">Success!</div>';

        expect(compile($input))->toBe($output);
    });

    it('with static props containing dynamic characters like dollar signs', function () {
        $input = '<x-button wire:click="$refresh" />';
        $output = '<button type="button" wire:click="$refresh"></button>';

        expect(compile($input))->toBe($output);
    })->skip();

    it('dynamic slot', function () {
        $input = '<x-button>{{ $name }}</x-button>';
        $output = '<button type="button">{{ $name }}</button>';

        expect(compile($input))->toBe($output);
    });

    it('dynamic attributes', function () {
        $input = '<x-button :type="$type">Save</x-button>';
        $output = '<button type="{{ $type }}">Save</button>';

        expect(compile($input))->toBe($output);
    });

    it('dynamic short attributes', function () {
        $input = '<x-button :$type>Save</x-button>';
        $output = '<button type="{{ $type }}">Save</button>';

        expect(compile($input))->toBe($output);
    });

    it('dynamic echo attributes', function () {
        $input = '<x-button type="foo {{ $type }}">Save</x-button>';
        $output = '<button type="foo {{ $type }}">Save</button>';

        expect(compile($input))->toBe($output);
    });

    it('dynamic slot with unfoldable component', function () {
        $input = '<x-button><x-impure-button>{{ $name }}</x-impure-button></x-button>';
        $output = '<button type="button"><x-impure-button>{{ $name }}</x-impure-button></button>';

        expect(compile($input))->toBe($output);
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
            \n    <button type="button">Edit</button>
            <button type="button">Delete</button>

        </div>
        HTML;

        expect(compile($input))->toBe($output);
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
            \n    <div class="alert">
                <button type="button">Save</button>
            </div>

        </div>
        HTML;

        expect(compile($input))->toBe($output);
    });

    it('self-closing component', function () {
        $input = '<x-alert message="Success!" />';
        $output = '<div class="alert">Success!</div>';

        expect(compile($input))->toBe($output);
    });

    it('component without @pure is not folded', function () {
        $input = '<x-impure-button>Save</x-impure-button>';
        $output = '<x-impure-button>Save</x-impure-button>';

        expect(compile($input))->toBe($output);
    });

    it('throws exception for invalid @pure usage', function () {
        // Test the folder validation directly
        $folder = app('blaze')->folder();
        $componentNode = new \Livewire\Blaze\Nodes\ComponentNode('invalid-pure', 'x', '', [], false);

        try {
            $folder->fold($componentNode);
            expect(false)->toBeTrue('Exception should have been thrown');
        } catch (\Livewire\Blaze\Exceptions\InvalidPureUsageException $e) {
            expect($e->getMessage())->toContain('Invalid @pure usage');
            expect($e->getComponentPath())->toContain('invalid-pure.blade.php');
            expect($e->getProblematicPattern())->toBe('\\$errors');
        }
    });

    it('named slots', function () {
        $input = '<x-modal><x-slot name="header">Modal Title</x-slot><x-slot name="footer">Footer Content</x-slot>Main content</x-modal>';

        $output = '<div class="modal">
    <div class="modal-header">Modal Title</div>
    <div class="modal-body">Main content</div>
    <div class="modal-footer">Footer Content</div>
</div>';

        expect(compile($input))->toBe($output);
    });
});
