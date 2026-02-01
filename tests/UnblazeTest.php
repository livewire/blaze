<?php

use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Blade;

describe('unblaze directive', function () {
    beforeEach(function () {
        // Configure Blade to find our test components
        app('blade.compiler')->anonymousComponentNamespace('', 'x');
        app('blade.compiler')->anonymousComponentPath(__DIR__.'/fixtures/components');

        // Add view path for our test pages
        View::addLocation(__DIR__.'/fixtures/pages');
    });

    it('folds component but preserves unblaze block', function () {
        $input = '<x-with-unblaze />';

        $compiled = app('blaze')->compile($input);

        // The component should be folded
        expect($compiled)->toContain('<div class="container">');
        expect($compiled)->toContain('<h1>Static Header</h1>');
        expect($compiled)->toContain('<footer>Static Footer</footer>');

        // The unblaze block should be preserved as dynamic content
        expect($compiled)->toContain('{{ $dynamicValue }}');
        expect($compiled)->not->toContain('<x-with-unblaze');
    });

    it('preserves unblaze content without folding the dynamic parts', function () {
        $input = '<x-with-unblaze />';

        $compiled = app('blaze')->compile($input);

        // Static parts should be folded
        expect($compiled)->toContain('Static Header');
        expect($compiled)->toContain('Static Footer');

        // Dynamic parts inside @unblaze should remain dynamic
        expect($compiled)->toContain('$dynamicValue');
    });

    it('handles unblaze with scope parameter', function () {
        $input = '<?php $message = "Hello World"; ?> <x-with-unblaze-scope :message="$message" />';

        $compiled = app('blaze')->compile($input);

        // The component should be folded
        expect($compiled)->toContain('<div class="wrapper">');
        expect($compiled)->toContain('<h2>Title</h2>');
        expect($compiled)->toContain('<p>Static paragraph</p>');

        // The scope should be captured and made available
        expect($compiled)->toContain('$scope');

        $rendered = Blade::render($compiled);

        // This fails. Rendered content has <div data-name="Hello World">{{ $message }}</div>
        expect($rendered)->toContain('<div class="dynamic">Hello World</div>');
    });

    it('encodes scope into compiled view for runtime access', function () {
        $input = '<?php $message = "Test Message"; ?> <x-with-unblaze-scope :message="$message" />';

        $compiled = app('blaze')->compile($input);

        // Should contain PHP code to set up the scope
        expect($compiled)->toMatch('/\$scope\s*=\s*array\s*\(/');

        // The dynamic content should reference $scope
        expect($compiled)->toContain('$scope[\'message\']');
    });

    it('renders unblaze component correctly at runtime', function () {
        $template = '<?php $message = "Runtime Test"; ?> <x-with-unblaze-scope :message="$message" />';

        $rendered = \Illuminate\Support\Facades\Blade::render($template);

        // Static content should be present
        expect($rendered)->toContain('<div class="wrapper">');
        expect($rendered)->toContain('<h2>Title</h2>');
        expect($rendered)->toContain('<p>Static paragraph</p>');

        // Dynamic content should be rendered with the scope value
        // Note: The actual rendering of scope variables happens at runtime
        expect($rendered)->toContain('class="dynamic"');
    });

    it('allows punching a hole in static component for dynamic section', function () {
        $input = <<<'BLADE'
<x-card>
    Static content here
    @unblaze
        <span>{{ $dynamicValue }}</span>
    @endunblaze
    More static content
</x-card>
BLADE;

        $compiled = app('blaze')->compile($input);

        // Card should be folded
        expect($compiled)->toContain('<div class="card">');
        expect($compiled)->toContain('Static content here');
        expect($compiled)->toContain('More static content');

        // But dynamic part should be preserved
        expect($compiled)->toContain('{{ $dynamicValue }}');
        expect($compiled)->not->toContain('<x-card>');
    });

    it('supports multiple unblaze blocks in same component', function () {
        $template = <<<'BLADE'
@blaze
<div>
    <p>Static 1</p>
    @unblaze
        <span>{{ $dynamic1 }}</span>
    @endunblaze
    <p>Static 2</p>
    @unblaze
        <span>{{ $dynamic2 }}</span>
    @endunblaze
    <p>Static 3</p>
</div>
BLADE;

        $compiled = app('blaze')->compile($template);

        // All static parts should be folded
        expect($compiled)->toContain('<p>Static 1</p>');
        expect($compiled)->toContain('<p>Static 2</p>');
        expect($compiled)->toContain('<p>Static 3</p>');

        // Both dynamic parts should be preserved
        expect($compiled)->toContain('{{ $dynamic1 }}');
        expect($compiled)->toContain('{{ $dynamic2 }}');
    });

    it('handles nested components with unblaze', function () {
        $input = <<<'BLADE'
<x-card>
    <x-button>Static Button</x-button>
    @unblaze
        <x-button>{{ $dynamicLabel }}</x-button>
    @endunblaze
</x-card>
BLADE;

        $compiled = app('blaze')->compile($input);

        // Outer card and static button should be folded
        expect($compiled)->toContain('<div class="card">');
        expect($compiled)->toContain('Static Button');

        // Dynamic button inside unblaze should be preserved
        expect($compiled)->toContain('{{ $dynamicLabel }}');
    });

    it('compiles blaze-enabled components inside unblaze blocks', function () {
        $input = <<<'BLADE'
@unblaze
<x-blaze-button>{{ $dynamicLabel }}</x-blaze-button>
@endunblaze
BLADE;

        $compiled = app('blaze')->compile($input);

        // The blaze-button component inside @unblaze should be compiled to function calls
        expect($compiled)->toContain('$__blaze->ensureCompiled');

        // The blaze-button should NOT remain as raw component tag
        expect($compiled)->not->toContain('<x-blaze-button>');
    });

    it('static folded content with random strings stays the same between renders', function () {
        // First render
        $render1 = \Illuminate\Support\Facades\Blade::render('<x-random-static />');

        // Second render
        $render2 = \Illuminate\Support\Facades\Blade::render('<x-random-static />');

        // The random string should be the same because it was folded at compile time
        expect($render1)->toBe($render2);

        // Verify it contains the static structure
        expect($render1)->toContain('class="static-component"');
        expect($render1)->toContain('This should be folded and not change between renders');
    });

    it('unblazed dynamic content changes between renders while static parts stay the same', function () {
        // First render with a value
        $render1 = \Illuminate\Support\Facades\Blade::render('<x-mixed-random />', ['dynamicValue' => 'first-value']);

        // Second render with a different value
        $render2 = \Illuminate\Support\Facades\Blade::render('<x-mixed-random />', ['dynamicValue' => 'second-value']);

        // Extract the static parts (header and footer with random strings)
        preg_match('/<h1>Static Random: (.+?)<\/h1>/', $render1, $matches1);
        preg_match('/<h1>Static Random: (.+?)<\/h1>/', $render2, $matches2);
        $staticRandom1 = $matches1[1] ?? '';
        $staticRandom2 = $matches2[1] ?? '';

        // The static random strings should be IDENTICAL (folded at compile time)
        expect($staticRandom1)->toBe($staticRandom2);
        expect($staticRandom1)->not->toBeEmpty();

        // But the dynamic parts should be DIFFERENT
        expect($render1)->toContain('Dynamic value: first-value');
        expect($render2)->toContain('Dynamic value: second-value');
        expect($render1)->not->toContain('second-value');
        expect($render2)->not->toContain('first-value');
    });

    it('multiple renders of unblaze component proves folding optimization', function () {
        // Render the same template multiple times with different dynamic values
        $renders = [];
        foreach (['one', 'two', 'three'] as $value) {
            $renders[] = \Illuminate\Support\Facades\Blade::render(
                '<x-mixed-random />',
                ['dynamicValue' => $value]
            );
        }

        // Extract the static footer random string from each render
        $staticFooters = [];
        foreach ($renders as $render) {
            preg_match('/<footer>Static Footer: (.+?)<\/footer>/', $render, $matches);
            $staticFooters[] = $matches[1] ?? '';
        }

        // All static random strings should be IDENTICAL (proving they were folded)
        expect($staticFooters[0])->toBe($staticFooters[1]);
        expect($staticFooters[1])->toBe($staticFooters[2]);
        expect($staticFooters[0])->not->toBeEmpty();

        // But each render should have its unique dynamic value
        expect($renders[0])->toContain('Dynamic value: one');
        expect($renders[1])->toContain('Dynamic value: two');
        expect($renders[2])->toContain('Dynamic value: three');
    });
});

describe('unblaze validation', function () {
    beforeEach(function () {
        app('blade.compiler')->anonymousComponentNamespace('', 'x');
        app('blade.compiler')->anonymousComponentPath(__DIR__.'/fixtures/components');
    });

    it('allows $errors inside @unblaze blocks', function () {
        $input = '<x-with-errors-inside-unblaze />';

        // Should not throw an exception
        $compiled = app('blaze')->compile($input);

        expect($compiled)->toContain('form-input');
        expect($compiled)->toContain('$errors');
    });

    it('throws exception for $errors outside @unblaze blocks', function () {
        expect(fn () => app('blaze')->compile('<x-with-errors-outside-unblaze />'))
            ->toThrow(\Livewire\Blaze\Exceptions\InvalidBlazeFoldUsageException::class);
    });

    it('allows @csrf inside @unblaze blocks', function () {
        $input = '<x-with-csrf-inside-unblaze />';

        // Should not throw an exception
        $compiled = app('blaze')->compile($input);

        expect($compiled)->toContain('form-wrapper');
    });

    it('allows request() inside @unblaze blocks', function () {
        $input = '<x-with-request-inside-unblaze />';

        // Should not throw an exception
        $compiled = app('blaze')->compile($input);

        expect($compiled)->toContain('<nav>');
        expect($compiled)->toContain('request()');
    });

    it('still validates problematic patterns in static parts of component', function () {
        // Create a component with $errors in static part and @unblaze
        $componentPath = __DIR__.'/fixtures/components/mixed-errors.blade.php';
        file_put_contents($componentPath, '@blaze(fold: true)
<div>
    <p>{{ $errors->count() }}</p>
    @unblaze
        <span>{{ $errors->first() }}</span>
    @endunblaze
</div>');

        try {
            expect(fn () => app('blaze')->compile('<x-mixed-errors />'))
                ->toThrow(\Livewire\Blaze\Exceptions\InvalidBlazeFoldUsageException::class);
        } finally {
            unlink($componentPath);
        }
    });
});
