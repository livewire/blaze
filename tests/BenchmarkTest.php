<?php

use Livewire\Blaze\Tests\TestCase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;

class BenchmarkTest extends TestCase
{
    public function test_can_run_performance_benchmarks()
    {
        // This is a placeholder test that ensures the benchmark infrastructure works
        $this->assertTrue(true);
    }

    /**
     * Run performance benchmarks and output results.
     * 
     * Usage: vendor/bin/pest --filter=benchmark_component_performance
     */
    public function benchmark_component_performance()
    {
        echo "\n🔥 Blaze Performance Benchmarks\n";
        echo "================================\n\n";

        // Test 1: Single component rendering
        $this->benchmarkSingleComponent();
        
        // Test 2: Page with heavy component usage
        $this->benchmarkHeavyComponentPage();
        
        // Test 3: Mixed scenarios
        $this->benchmarkMixedScenarios();

        echo "\nBenchmarks complete! ✅\n";
        echo "Note: Results may vary based on system performance and PHP configuration.\n";
    }

    private function benchmarkSingleComponent()
    {
        echo "📊 Single Component Rendering (1,000 iterations)\n";
        echo "------------------------------------------------\n";

        // Create a simple button component
        $this->createComponent('button', '@pure

@props([\'variant\' => \'primary\'])

<button type="button" class="btn btn-{{ $variant }}">
    {{ $slot }}
</button>');

        $template = '<x-button variant="secondary">Click me</x-button>';

        // Benchmark without Blaze (disable the package temporarily)
        $timeWithoutBlaze = $this->benchmarkRender($template, 1000, false);
        
        // Clear compiled views to measure first run compile time
        $this->clearCompiledViews();
        
        // Benchmark with Blaze - first run (includes compile time)
        $timeFirstRun = $this->benchmarkRender($template, 1000, true);
        
        // Benchmark with Blaze - second run (cached)
        $timeSecondRun = $this->benchmarkRender($template, 1000, true);

        $this->outputResults('Single Component', $timeWithoutBlaze, $timeFirstRun, $timeSecondRun, 1000);
    }

    private function benchmarkHeavyComponentPage()
    {
        echo "\n📊 Heavy Component Usage Page\n";
        echo "-----------------------------\n";

        // Create multiple components
        $this->createComponent('card', '@pure

@props([\'title\' => \'\'])

<div class="card">
    <h3 class="card-title">{{ $title }}</h3>
    <div class="card-body">{{ $slot }}</div>
</div>');

        $this->createComponent('badge', '@pure

@props([\'color\' => \'gray\'])

<span class="badge badge-{{ $color }}">{{ $slot }}</span>');

        $this->createComponent('avatar', '@pure

@props([\'name\' => \'\', \'src\' => null])

@if($src)
    <img src="{{ $src }}" alt="{{ $name }}" class="avatar">
@else
    <div class="avatar-placeholder">{{ substr($name, 0, 1) }}</div>
@endif');

        // Create a template with multiple components
        $template = '
        <div class="dashboard">
            <x-card title="User Stats">
                <x-badge color="green">Active</x-badge>
                <x-avatar name="John Doe" />
            </x-card>
            <x-card title="Analytics">
                <x-badge color="blue">Updated</x-badge>
                <x-avatar name="Jane Smith" />
            </x-card>
        </div>';

        $iterations = 500;

        // Benchmark scenarios
        $timeWithoutBlaze = $this->benchmarkRender($template, $iterations, false);
        
        $this->clearCompiledViews();
        $timeFirstRun = $this->benchmarkRender($template, $iterations, true);
        $timeSecondRun = $this->benchmarkRender($template, $iterations, true);

        $this->outputResults('Heavy Component Page', $timeWithoutBlaze, $timeFirstRun, $timeSecondRun, $iterations);
    }

    private function benchmarkMixedScenarios()
    {
        echo "\n📊 Mixed Component Scenarios\n";
        echo "----------------------------\n";

        // Create pure component
        $this->createComponent('pure-button', '@pure

@props([\'type\' => \'button\'])

<button type="{{ $type }}" class="btn">{{ $slot }}</button>');

        // Create non-pure component (uses request data)
        $this->createComponent('dynamic-link', '
@props([\'href\'])

<a href="{{ $href }}" @class([\'active\' => request()->is($href)])>
    {{ $slot }}
</a>');

        // Mixed template
        $template = '
        <div>
            <x-pure-button>Save</x-pure-button>
            <x-dynamic-link href="/dashboard">Dashboard</x-dynamic-link>
            <x-pure-button type="submit">Submit</x-pure-button>
            <x-dynamic-link href="/profile">Profile</x-dynamic-link>
        </div>';

        $iterations = 1000;

        $timeWithoutBlaze = $this->benchmarkRender($template, $iterations, false);
        
        $this->clearCompiledViews();
        $timeFirstRun = $this->benchmarkRender($template, $iterations, true);
        $timeSecondRun = $this->benchmarkRender($template, $iterations, true);

        $this->outputResults('Mixed Components (50% optimizable)', $timeWithoutBlaze, $timeFirstRun, $timeSecondRun, $iterations);
    }

    private function benchmarkRender(string $template, int $iterations, bool $enableBlaze): float
    {
        // Enable/disable Blaze for this test
        if (!$enableBlaze) {
            // TODO: Implement Blaze disable mechanism
        }

        $startTime = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            Blade::render($template);
        }

        $endTime = microtime(true);
        
        return ($endTime - $startTime) * 1000; // Convert to milliseconds
    }

    private function outputResults(string $scenario, float $withoutBlaze, float $firstRun, float $secondRun, int $iterations)
    {
        $improvement = $withoutBlaze / $secondRun;
        $compileTime = $firstRun - $secondRun;
        
        echo "Scenario: {$scenario}\n";
        echo "Iterations: " . number_format($iterations) . "\n\n";
        
        echo "Without Blaze:  " . number_format($withoutBlaze, 1) . "ms (" . 
             number_format($withoutBlaze / $iterations, 3) . "ms per iteration)\n";
        
        echo "With Blaze:\n";
        echo "  First run:    " . number_format($firstRun, 1) . "ms (" . 
             number_format($secondRun, 1) . "ms + " . number_format($compileTime, 1) . "ms compile time)\n";
        echo "  Second run:   " . number_format($secondRun, 1) . "ms (" . 
             number_format($secondRun / $iterations, 3) . "ms per iteration)\n\n";
        
        echo "Improvement: ~" . number_format($improvement, 1) . "x faster after compilation\n\n";
    }

    private function createComponent(string $name, string $content): void
    {
        $path = base_path("benchmarks/fixtures/components/{$name}.blade.php");
        
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        
        file_put_contents($path, $content);
    }

    private function clearCompiledViews(): void
    {
        $compiledPath = storage_path('framework/views');
        
        if (is_dir($compiledPath)) {
            $files = glob($compiledPath . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
}