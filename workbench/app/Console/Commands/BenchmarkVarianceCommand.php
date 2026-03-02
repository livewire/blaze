<?php

namespace Workbench\App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

class BenchmarkVarianceCommand extends BenchmarkCommand
{
    protected $signature = 'benchmark:variance
        {--runs=5 : Number of benchmark runs after the initial snapshot run}
        {--iterations=2500 : Number of component renders per benchmark}
        {--rounds=100 : Number of timed rounds per benchmark}
        {--warmup=2 : Number of untimed warmup rounds}
        {--filter-outliers : Exclude outlier rounds using the IQR method}
        {--json : Output results as JSON}
        {--ci : Output a markdown table with no progress (for CI)}';

    protected $description = 'Run benchmarks multiple times and report variance (min/max/avg) with change deltas';

    public function handle(): int
    {
        $this->iterations = (int) $this->option('iterations');
        $this->rounds = (int) $this->option('rounds');
        $this->warmupRounds = (int) $this->option('warmup');
        $runs = (int) $this->option('runs');

        if ($runs < 1) {
            $this->error('--runs must be at least 1.');

            return Command::FAILURE;
        }

        $totalRuns = $runs + 1;
        $quiet = $this->option('json') || $this->option('ci');
        $commandStart = microtime(true);

        // Always suppress inner progress bars.
        $this->input->setOption('ci', true);

        $runDurations = [];

        if (! $quiet) {
            $bar = $this->output->createProgressBar($totalRuns);
            $bar->setFormat(' %current%/%max% [%bar%] %message%');
            $bar->setMessage('Snapshot...');
            $bar->start();
        }

        Artisan::call('view:clear');

        // Step 1: Snapshot run
        $t = microtime(true);
        $snapshotResults = $this->runBenchmarks();
        $runDurations[] = microtime(true) - $t;
        $this->saveSnapshot($snapshotResults);

        if (! $quiet) {
            $bar->advance();
            $bar->setMessage('Benchmarking...');
        }

        // Step 2: Benchmark runs
        $allRuns = [];

        for ($i = 0; $i < $runs; $i++) {
            Artisan::call('view:clear');
            $t = microtime(true);
            $allRuns[] = $this->runBenchmarks();
            $runDurations[] = microtime(true) - $t;

            if (! $quiet) {
                $bar->advance();
            }
        }

        if (! $quiet) {
            $bar->setMessage('Done!');
            $bar->finish();
            $this->newLine();
        }

        $avgRunDuration = round(array_sum($runDurations) / count($runDurations), 2);
        $totalDuration = round(microtime(true) - $commandStart, 2);

        // Step 3: Display variance report
        if ($this->option('json')) {
            $this->outputJson($snapshotResults, $allRuns, $avgRunDuration, $totalDuration);
        } elseif ($this->option('ci')) {
            $this->outputVarianceMarkdown($snapshotResults, $allRuns, $avgRunDuration, $totalDuration);
        } else {
            $this->displayVarianceResults($snapshotResults, $allRuns, $avgRunDuration, $totalDuration);
        }

        return Command::SUCCESS;
    }

    protected function displayVarianceResults(array $snapshot, array $allRuns, float $avgRunDuration, float $totalDuration): void
    {
        $stddev = function ($values) {
            $count = $values->count();
            if ($count < 2) return 0.0;
            $mean = $values->avg();
            $sumSquares = $values->reduce(fn ($carry, $v) => $carry + ($v - $mean) ** 2, 0);
            return round(sqrt($sumSquares / ($count - 1)), 2);
        };

        $benchmarkNames = array_keys($snapshot);
        $headers = ['', 'Blade', 'Blaze', 'Improvement'];
        $rows = [];

        foreach ($benchmarkNames as $name) {
            $snapshotImprovement = $this->improvement($snapshot[$name]);

            $bladeChanges = collect($allRuns)->map(fn ($run) => $this->percentChange($snapshot[$name]['blade_ms'], $run[$name]['blade_ms']));
            $blazeChanges = collect($allRuns)->map(fn ($run) => $this->percentChange($snapshot[$name]['blaze_ms'], $run[$name]['blaze_ms']));
            $improvementChanges = collect($allRuns)->map(fn ($run) => round($this->improvement($run[$name]) - $snapshotImprovement, 1));

            $rows[] = [
                'Snapshot',
                $this->formatTime($snapshot[$name]['blade_ms']),
                $this->formatTime($snapshot[$name]['blaze_ms']),
                $snapshotImprovement.'%',
            ];

            $rows[] = [
                'Variance',
                $this->formatVarianceRange($bladeChanges->min(), $bladeChanges->max()),
                $this->formatVarianceRange($blazeChanges->min(), $blazeChanges->max()),
                $this->formatVarianceRange($improvementChanges->min(), $improvementChanges->max()),
            ];

            $rows[] = [
                'Std Dev',
                '±'.$stddev($bladeChanges).'%',
                '±'.$stddev($blazeChanges).'%',
                '±'.$stddev($improvementChanges).'%',
            ];
        }

        $this->newLine(2);
        $this->table($headers, $rows);

        $this->newLine();
        $this->comment(
            count($allRuns)." runs x {$this->rounds} rounds x {$this->iterations} iterations"
            .($this->option('filter-outliers') ? ' (outliers excluded)' : '')
            .", ~{$avgRunDuration}s/run, {$totalDuration}s total"
        );
    }

    protected function outputVarianceMarkdown(array $snapshot, array $allRuns, float $avgRunDuration, float $totalDuration): void
    {
        $stddev = function ($values) {
            $count = $values->count();
            if ($count < 2) return 0.0;
            $mean = $values->avg();
            $sumSquares = $values->reduce(fn ($carry, $v) => $carry + ($v - $mean) ** 2, 0);
            return round(sqrt($sumSquares / ($count - 1)), 2);
        };

        $benchmarkNames = array_keys($snapshot);
        $headers = ['', 'Blade', 'Blaze', 'Improvement'];
        $rows = [];

        foreach ($benchmarkNames as $name) {
            $snapshotImprovement = $this->improvement($snapshot[$name]);

            $bladeChanges = collect($allRuns)->map(fn ($run) => $this->percentChange($snapshot[$name]['blade_ms'], $run[$name]['blade_ms']));
            $blazeChanges = collect($allRuns)->map(fn ($run) => $this->percentChange($snapshot[$name]['blaze_ms'], $run[$name]['blaze_ms']));
            $improvementChanges = collect($allRuns)->map(fn ($run) => round($this->improvement($run[$name]) - $snapshotImprovement, 1));

            $rows[] = [
                'Snapshot',
                $this->formatTime($snapshot[$name]['blade_ms']),
                $this->formatTime($snapshot[$name]['blaze_ms']),
                $snapshotImprovement . '%',
            ];

            $rows[] = [
                'Variance',
                $this->formatVarianceRange($bladeChanges->min(), $bladeChanges->max()),
                $this->formatVarianceRange($blazeChanges->min(), $blazeChanges->max()),
                $this->formatVarianceRange($improvementChanges->min(), $improvementChanges->max()),
            ];

            $rows[] = [
                'Std Dev',
                '±' . $stddev($bladeChanges) . '%',
                '±' . $stddev($blazeChanges) . '%',
                '±' . $stddev($improvementChanges) . '%',
            ];
        }

        $allRows = collect([$headers, ...$rows]);
        $widths = collect($headers)->keys()->map(
            fn ($i) => $allRows->max(fn ($row) => mb_strlen($row[$i]))
        );

        $formatRow = fn ($cells) => '| ' . collect($cells)
            ->map(fn ($cell, $i) => Str::padRight($cell, $widths[$i]))
            ->implode(' | ') . ' |';

        $separator = '| ' . $widths->map(fn ($w) => str_repeat('-', $w))->implode(' | ') . ' |';

        $md = collect([
            '## Benchmark Variance Results',
            '',
            $formatRow($headers),
            $separator,
            ...collect($rows)->map($formatRow),
            '',
            '<sub>' . count($allRuns) . " runs x {$this->rounds} rounds x {$this->iterations} iterations"
                . ($this->option('filter-outliers') ? " &mdash; outliers excluded" : '')
                . ", ~{$avgRunDuration}s/run, {$totalDuration}s total"
                . '</sub>',
        ])->implode("\n");

        $this->output->writeln($md);
    }

    protected function saveSnapshot(array $results): void
    {
        $snapshot = [
            'iterations' => $this->iterations,
            'rounds' => $this->rounds,
            'benchmarks' => collect($results)->map(fn ($result) => [
                'blade_ms' => $result['blade_ms'],
                'blaze_ms' => $result['blaze_ms'],
                'improvement' => $this->improvement($result),
            ])->all(),
        ];

        \Illuminate\Support\Facades\File::put(
            $this->snapshotPath(),
            json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n"
        );
    }

    protected function outputJson(array $snapshot, array $allRuns, float $avgRunDuration, float $totalDuration): void
    {
        $benchmarks = [];

        foreach (array_keys($snapshot) as $name) {
            $snapshotImprovement = $this->improvement($snapshot[$name]);

            $bladeChanges = collect($allRuns)->map(fn ($run) => $this->percentChange($snapshot[$name]['blade_ms'], $run[$name]['blade_ms']));
            $blazeChanges = collect($allRuns)->map(fn ($run) => $this->percentChange($snapshot[$name]['blaze_ms'], $run[$name]['blaze_ms']));
            $improvementChanges = collect($allRuns)->map(fn ($run) => round($this->improvement($run[$name]) - $snapshotImprovement, 1));

            $stddev = function ($values) {
                $count = $values->count();
                if ($count < 2) return 0.0;
                $mean = $values->avg();
                $sumSquares = $values->reduce(fn ($carry, $v) => $carry + ($v - $mean) ** 2, 0);
                return round(sqrt($sumSquares / ($count - 1)), 2);
            };

            $benchmarks[$name] = [
                'snapshot' => [
                    'blade_ms' => $snapshot[$name]['blade_ms'],
                    'blaze_ms' => $snapshot[$name]['blaze_ms'],
                    'improvement' => $snapshotImprovement,
                ],
                'variance' => [
                    'blade' => ['min' => $bladeChanges->min(), 'max' => $bladeChanges->max(), 'stddev' => $stddev($bladeChanges)],
                    'blaze' => ['min' => $blazeChanges->min(), 'max' => $blazeChanges->max(), 'stddev' => $stddev($blazeChanges)],
                    'improvement' => ['min' => $improvementChanges->min(), 'max' => $improvementChanges->max(), 'stddev' => $stddev($improvementChanges)],
                ],
            ];
        }

        $this->output->writeln(json_encode([
            'iterations' => $this->iterations,
            'rounds' => $this->rounds,
            'runs' => count($allRuns),
            'avg_run_duration_s' => $avgRunDuration,
            'total_duration_s' => $totalDuration,
            'filter_outliers' => (bool) $this->option('filter-outliers'),
            'benchmarks' => $benchmarks,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    protected function formatVarianceRange(float $min, float $max): string
    {
        $fmt = function (float $v): string {
            $rounded = round($v, 1);
            if ($rounded == 0) {
                return '0%';
            }
            $sign = $rounded > 0 ? '+' : '';

            return $sign.$rounded.'%';
        };

        return $fmt($min).' / '.$fmt($max);
    }

    protected function percentChange(float $old, float $new): float
    {
        return $old > 0 ? round(($new - $old) / $old * 100, 1) : 0;
    }
}
