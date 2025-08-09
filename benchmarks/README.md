# Blaze Performance Benchmarks

This directory contains benchmark scripts and fixtures for measuring Blaze performance improvements.

## Running Benchmarks

### Option 1: Standalone Script
```bash
php benchmark.php
```

This will run all benchmarks and output results that can be copied to the main README.

### Option 2: Via Pest Test
```bash
vendor/bin/pest --filter=benchmark_component_performance
```

This runs benchmarks within the Laravel testing environment.

## Benchmark Scenarios

### 1. Single Component Rendering
Tests a simple button component rendered 1,000 times to measure basic optimization overhead.

### 2. Heavy Component Usage
Tests a page with multiple different components used many times (cards, badges, navigation).

### 3. Mixed Scenarios  
Tests pages with both `@pure` optimizable components and dynamic components that can't be optimized.

## Fixture Components

The `fixtures/components/` directory contains test components:

- **button.blade.php** - Pure button component (optimizable)
- **card.blade.php** - Pure card component (optimizable)  
- **badge.blade.php** - Pure badge component (optimizable)
- **dynamic-link.blade.php** - Link with request-dependent styling (not optimizable)
- **nav-item.blade.php** - Navigation item with active state (not optimizable)

## Output Format

Benchmarks generate output suitable for copying into the main README:

```
📊 Single Component Rendering (1,000 iterations)
------------------------------------------------
```
Without Blaze:  125ms (0.125ms per component)
With Blaze:     
  First run:    13ms  (8ms + 5ms compile time)
  Second run:   8ms   (0.008ms per component)

Improvement: ~15.6x faster after compilation
```

## Adding New Benchmarks

1. Create fixture components in `fixtures/components/`
2. Add benchmark method to `BenchmarkTest.php` or `benchmark.php`
3. Follow the naming pattern: `benchmark{ScenarioName}()`
4. Use `outputResults()` for consistent formatting

## Notes

- Benchmarks create temporary files that are automatically cleaned up
- Results may vary based on system performance and PHP configuration
- First run includes compilation overhead, second run shows cached performance
- All fixture components use realistic Tailwind CSS classes for accurate measurements