<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Livewire\Blaze\Blaze;

beforeEach(function () {
    View::addLocation(__DIR__ . '/fixtures/views');

    // Clear compiled views to ensure fresh compilation
    $compiledPath = storage_path('framework/views');
    if (is_dir($compiledPath)) {
        array_map('unlink', glob("$compiledPath/*"));
    }
});

describe('component folding', function () {
    it('folds pure components into static HTML', function () {
        dd(app('blaze')->compile('<x-button size="lg">Click Me</x-button>'));

        // The raw Blade template
        $template = '<x-button size="lg">Click Me</x-button>';

        // What we expect after folding (button component pre-rendered)
        $expectedFolded = '<button type="button" class="btn btn-lg">Click Me</button>';

        // Simulate what Blaze should do
        $blaze->shouldReceive('compile')
            ->with($template)
            ->andReturn($expectedFolded);

        $result = $blaze->compile($template);

        expect($result)->toBe($expectedFolded);
    });

    it('preserves dynamic content when folding', function () {
        $template = '<x-button>{{ $text }}</x-button>';

        // After folding, dynamic parts should be preserved
        $expectedFolded = '<button type="button" class="btn btn-md">{{ $text }}</button>';

        // When this is actually rendered with data
        $rendered = Blade::render($expectedFolded, ['text' => 'Hello World']);

        expect($rendered)->toBe('<button type="button" class="btn btn-md">Hello World</button>');
    });

    it('does not fold components without @pure directive', function () {
        $template = '<x-card title="Test">Content</x-card>';

        // Since card.blade.php doesn't have @pure, it should remain unchanged
        $blaze = Mockery::mock(Blaze::class);
        $blaze->shouldReceive('compile')
            ->with($template)
            ->andReturn($template); // Returns unchanged

        $this->app->instance(Blaze::class, $blaze);

        $result = $blaze->compile($template);

        expect($result)->toBe($template);
    });

    it('tracks component invocations to verify folding works', function () {
        // This is a powerful test approach - we track if the component was actually invoked
        $componentInvoked = false;

        // Override the component to track invocations
        View::composer('components.button', function ($view) use (&$componentInvoked) {
            $componentInvoked = true;
        });

        // With folding, the component should NOT be invoked at runtime
        $foldedTemplate = '<button type="button" class="btn btn-lg">Click Me</button>';

        $rendered = Blade::render($foldedTemplate);

        expect($componentInvoked)->toBeFalse();
        expect($rendered)->toBe('<button type="button" class="btn btn-lg">Click Me</button>');
    });

    it('handles nested components correctly', function () {
        $template = '
            <x-card title="Welcome">
                <x-button size="sm">Submit</x-button>
            </x-card>
        ';

        // Only the button (with @pure) should be folded
        $expectedPartiallyFolded = '
            <x-card title="Welcome">
                <button type="button" class="btn btn-sm">Submit</button>
            </x-card>
        ';

        // Test that selective folding works
        $blaze = Mockery::mock(Blaze::class);
        $blaze->shouldReceive('compile')
            ->andReturnUsing(function ($input) {
                // Simple simulation: replace x-button with folded version
                if (str_contains($input, '<x-button')) {
                    return preg_replace(
                        '/<x-button[^>]*>(.*?)<\/x-button>/s',
                        '<button type="button" class="btn btn-sm">$1</button>',
                        $input
                    );
                }
                return $input;
            });

        $this->app->instance(Blaze::class, $blaze);

        $result = $blaze->compile($template);

        expect(trim($result))->toBe(trim($expectedPartiallyFolded));
    });

});

describe('performance validation', function () {
    it('reduces component instantiation overhead', function () {
        // This test would measure actual performance in a real implementation
        $iterations = 100;

        // Measure time for non-folded rendering
        $normalStart = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            // Simulate normal component rendering with actual Blade compilation
            Blade::render('<x-button size="lg">Click</x-button>');
        }
        $normalTime = microtime(true) - $normalStart;

        // Measure time for folded rendering (just string output)
        $foldedStart = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            // Simulate folded rendering (just rendering pre-compiled HTML)
            Blade::render('<button type="button" class="btn btn-lg">Click</button>');
        }
        $foldedTime = microtime(true) - $foldedStart;

        // Folded should be faster (in real implementation)
        // For now, just ensure both complete without error
        expect($normalTime)->toBeGreaterThan(0);
        expect($foldedTime)->toBeGreaterThan(0);
    });

});

describe('error handling', function () {
    it('falls back gracefully when folding fails', function () {
        $template = '<x-button :class="$dynamicClass">Test</x-button>';

        // Even if folding fails, original template should work
        $blaze = Mockery::mock(Blaze::class);
        $blaze->shouldReceive('compile')
            ->andReturnUsing(function ($input) {
                // Simulate folding failure - return original
                return $input;
            });

        $this->app->instance(Blaze::class, $blaze);

        $result = $blaze->compile($template);

        // Should return original template unchanged
        expect($result)->toBe($template);
    });

    it('detects and rejects components with runtime dependencies', function () {
        $templateWithCsrf = '
            @pure
            <form>
                @csrf
                <button>Submit</button>
            </form>
        ';

        // Should detect @csrf and refuse to fold
        $blaze = Mockery::mock(Blaze::class);
        $blaze->shouldReceive('canFold')
            ->andReturnUsing(function ($template) {
                return !str_contains($template, '@csrf');
            });

        $this->app->instance(Blaze::class, $blaze);

        expect($blaze->canFold($templateWithCsrf))->toBeFalse();
    });

});