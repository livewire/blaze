<?php

namespace Workbench\App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Benchmark;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\View;

class BenchmarkCommand extends Command
{
    protected $signature = 'benchmark
        {--iterations=10000 : Number of component renders per benchmark}
        {--rounds=25 : Number of timed rounds per benchmark}
        {--warmup=2 : Number of untimed warmup rounds}
        {--snapshot : Save results as the baseline snapshot}
        {--ci : Output a markdown table with no progress (for CI)}';

    protected $description = 'Run Blaze performance benchmarks';

    protected int $iterations;

    protected int $rounds;

    protected int $warmupRounds;

    public function handle(): int
    {
        $this->iterations = (int) $this->option('iterations');
        $this->rounds = (int) $this->option('rounds');
        $this->warmupRounds = (int) $this->option('warmup');

        Artisan::call('view:clear');

        $results = $this->runBenchmarks();

        if ($this->option('ci')) {
            $this->outputMarkdown($results);
        } else {
            $this->displayResults($results);
        }

        if ($this->option('snapshot')) {
            $this->saveSnapshot($results);
        }

        return Command::SUCCESS;
    }

    protected function runBenchmarks(): array
    {
        $showProgress = ! $this->option('ci');
        $benchmarks = $this->getBenchmarks();
        $names = array_keys($benchmarks);

        if ($showProgress) {
            $this->info("Running benchmarks ({$this->iterations} iterations x {$this->rounds} rounds)...\n");
        }

        ob_start();

        // Warmup: compile views and stabilize CPU/opcache.
        if ($showProgress) {
            $this->output->write('  Warming up...');
        }

        foreach ($benchmarks as $benchmark) {
            for ($w = 0; $w < $this->warmupRounds; $w++) {
                $this->measureView($benchmark['blade']);
                $this->measureView($benchmark['blaze']);
            }
        }

        if ($showProgress) {
            $this->output->writeln(' done');
        }

        // Initialize per-benchmark storage.
        $bladeTimes = array_fill_keys($names, []);
        $blazeTimes = array_fill_keys($names, []);

        // Timed rounds: interleave all benchmarks within each round and
        // shuffle the order to distribute thermal drift and system noise
        // evenly instead of concentrating it on whichever benchmark runs last.
        if ($showProgress) {
            $this->output->write('  Benchmarking');
        }

        for ($r = 0; $r < $this->rounds; $r++) {
            if ($showProgress) {
                $this->output->write('.');
            }

            $order = $names;
            shuffle($order);

            foreach ($order as $name) {
                gc_collect_cycles();
                $bladeTimes[$name][] = $this->measureView($benchmarks[$name]['blade']);
                gc_collect_cycles();
                $blazeTimes[$name][] = $this->measureView($benchmarks[$name]['blaze']);
            }
        }

        if ($showProgress) {
            $this->output->writeln(' done');
        }

        ob_end_clean();

        // Build results using the median of each benchmark's rounds.
        $results = [];

        foreach ($names as $name) {
            $results[$name] = [
                'blade_ms' => $this->median($bladeTimes[$name]),
                'blaze_ms' => $this->median($blazeTimes[$name]),
                'blade_times' => $bladeTimes[$name],
                'blaze_times' => $blazeTimes[$name],
            ];
        }

        return $results;
    }

    // region Display

    protected function buildTable(array $results): array
    {
        $snapshot = $this->option('snapshot') ? null : $this->loadSnapshot();

        $headers = ['Benchmark', 'Blade', 'Blaze', 'Improvement'];
        $rows = [];

        foreach ($results as $name => $result) {
            $blade = $this->formatTime($result['blade_ms']);
            $blaze = $this->formatTime($result['blaze_ms']);
            $improvement = $this->improvement($result) . '%';

            if ($snapshot && isset($snapshot['benchmarks'][$name])) {
                $prev = $snapshot['benchmarks'][$name];

                $blade .= ' ' . $this->formatChange($prev['blade_ms'], $result['blade_ms'], $result['blade_times']);
                $blaze .= ' ' . $this->formatChange($prev['blaze_ms'], $result['blaze_ms'], $result['blaze_times']);

                $perRoundImprovements = array_map(
                    fn ($b, $z) => $b > 0 ? (1 - $z / $b) * 100 : 0,
                    $result['blade_times'],
                    $result['blaze_times'],
                );

                $improvement .= ' ' . $this->formatChange(
                    $prev['improvement'],
                    $this->improvement($result),
                    $perRoundImprovements,
                );
            }

            $rows[] = [$name, $blade, $blaze, $improvement];
        }

        return [$headers, $rows, $snapshot];
    }

    protected function displayResults(array $results): void
    {
        [$headers, $rows, $snapshot] = $this->buildTable($results);

        $this->newLine();
        $this->table($headers, $rows);

        $this->newLine();
        $this->info("{$this->iterations} iterations x {$this->rounds} rounds per benchmark");

        if ($snapshot) {
            $rounds = $snapshot['rounds'] ?? 1;
            $this->comment("Compared against snapshot ({$snapshot['iterations']} iterations x {$rounds} rounds)");
        }
    }

    protected function outputMarkdown(array $results): void
    {
        [$headers, $rows, $snapshot] = $this->buildTable($results);

        // Calculate the max width for each column.
        $widths = array_map('mb_strlen', $headers);

        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $widths[$i] = max($widths[$i], mb_strlen($cell));
            }
        }

        $md = "## Benchmark Results\n\n";

        // Header row.
        $md .= '|';
        foreach ($headers as $i => $header) {
            $md .= ' ' . str_pad($header, $widths[$i]) . ' |';
        }
        $md .= "\n";

        // Separator row.
        $md .= '|';
        foreach ($widths as $width) {
            $md .= ' ' . str_repeat('-', $width) . ' |';
        }
        $md .= "\n";

        // Data rows.
        foreach ($rows as $row) {
            $md .= '|';
            foreach ($row as $i => $cell) {
                $md .= ' ' . str_pad($cell, $widths[$i]) . ' |';
            }
            $md .= "\n";
        }

        $md .= "\n<sub>{$this->iterations} iterations x {$this->rounds} rounds per benchmark";

        if ($snapshot) {
            $md .= " &mdash; compared against committed snapshot";
        }

        $md .= "</sub>";

        $this->output->writeln($md);
    }

    // endregion

    // region Snapshot

    protected function saveSnapshot(array $results): void
    {
        $snapshot = [
            'iterations' => $this->iterations,
            'rounds' => $this->rounds,
            'benchmarks' => [],
        ];

        foreach ($results as $name => $result) {
            $snapshot['benchmarks'][$name] = [
                'blade_ms' => $result['blade_ms'],
                'blaze_ms' => $result['blaze_ms'],
                'improvement' => $this->improvement($result),
            ];
        }

        $path = $this->snapshotPath();

        file_put_contents($path, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

        $this->newLine();
        $this->info("Snapshot saved to {$path}");
    }

    protected function loadSnapshot(): ?array
    {
        $path = $this->snapshotPath();

        if (! file_exists($path)) {
            return null;
        }

        $data = json_decode(file_get_contents($path), true);

        if (! is_array($data) || ! isset($data['benchmarks'])) {
            return null;
        }

        return $data;
    }

    protected function snapshotPath(): string
    {
        $root = dirname(__DIR__, 4);

        return $this->option('ci')
            ? $root . '/.github/benchmark-snapshot.json'
            : $root . '/benchmark-snapshot.json';
    }

    // endregion

    // region Helpers

    protected function improvement(array $result): float
    {
        return $result['blade_ms'] > 0
            ? round((1 - $result['blaze_ms'] / $result['blade_ms']) * 100, 1)
            : 0;
    }

    protected function median(array $values): float
    {
        sort($values);

        $count = count($values);
        $mid = intdiv($count, 2);

        return $count % 2 === 0
            ? round(($values[$mid - 1] + $values[$mid]) / 2, 2)
            : round($values[$mid], 2);
    }

    protected function coefficientOfVariation(array $values): float
    {
        $count = count($values);

        if ($count < 2) {
            return 0.0;
        }

        $mean = array_sum($values) / $count;

        if ($mean == 0) {
            return 0.0;
        }

        $variance = array_sum(array_map(fn ($v) => ($v - $mean) ** 2, $values)) / ($count - 1);

        return sqrt($variance) / abs($mean) * 100;
    }

    /**
     * Format the percentage change between a snapshot value and the current
     * median, suppressing changes that fall within the measurement noise.
     *
     * Returns "(~)" when the change is not statistically significant, or
     * "(+X%)" / "(-X%)" when it exceeds the margin of error.
     */
    protected function formatChange(float $old, float $new, array $samples): string
    {
        if ($old == 0) {
            return '(~)';
        }

        $pctChange = ($new - $old) / abs($old) * 100;
        $cv = $this->coefficientOfVariation($samples);
        $n = count($samples);

        // Margin of error for the median at ~95% confidence.
        // SE_median ≈ 1.253 × σ/√n, expressed as a percentage via CV.
        $margin = 2 * 1.253 * $cv / sqrt(max($n, 1));

        // Suppress changes within statistical noise or below practical significance.
        // The 5% floor accounts for between-run variance (thermal drift, OS scheduling)
        // that a single run's within-run CV cannot capture.
        if (abs($pctChange) < max($margin, 5.0)) {
            return '(~)';
        }

        $sign = $pctChange > 0 ? '+' : '';

        return '(' . $sign . round($pctChange, 1) . '%)';
    }

    protected function getBenchmarks(): array
    {
        return [
            'No attributes' => [
                'blade' => 'bench.blade.no-attributes',
                'blaze' => 'bench.blaze.no-attributes',
            ],
            'Attributes only' => [
                'blade' => 'bench.blade.attributes',
                'blaze' => 'bench.blaze.attributes',
            ],
            'Attributes + merge()' => [
                'blade' => 'bench.blade.merge',
                'blaze' => 'bench.blaze.merge',
            ],
            'Attributes + class()' => [
                'blade' => 'bench.blade.class',
                'blaze' => 'bench.blaze.class',
            ],
            'Props + attributes' => [
                'blade' => 'bench.blade.props',
                'blaze' => 'bench.blaze.props',
            ],
            'Default slot' => [
                'blade' => 'bench.blade.slot',
                'blaze' => 'bench.blaze.slot',
            ],
            'Named slots' => [
                'blade' => 'bench.blade.named-slots',
                'blaze' => 'bench.blaze.named-slots',
            ],
            '@aware (nested)' => [
                'blade' => 'bench.blade.aware',
                'blaze' => 'bench.blaze.aware',
            ],
            'Attribute forwarding' => [
                'blade' => 'bench.blade.forwarding',
                'blaze' => 'bench.blaze.forwarding',
            ],
        ];
    }

    protected function renderView(string $view): string
    {
        return View::make($view, ['iterations' => $this->iterations])->render();
    }

    protected function measureView(string $view): float
    {
        return Benchmark::measure(fn () => $this->renderView($view));
    }

    protected function formatTime(float $ms): string
    {
        return number_format($ms, 2) . 'ms';
    }

    // endregion
}
