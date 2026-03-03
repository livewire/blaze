<?php

namespace Workbench\App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Used for measuring the reliability of the benchmark
 * in the CI and its variance from the snapshot.
 */
class BenchmarkVarianceCommand extends BenchmarkCommand
{
    protected $signature = 'benchmark:variance
        {benchmark : Name of the benchmark to run}
        {--runs=5 : Number of benchmark runs after the initial snapshot run}
        {--iterations=5000 : Number of component renders per benchmark}
        {--rounds=100 : Number of timed rounds per benchmark}
        {--warmup=2 : Number of untimed warmup rounds}
        {--attempts=5 : Number of attempts per run (forwarded to benchmark command)}
        {--json : Output results as JSON}
        {--ci : Output a markdown table with no progress (for CI)}';

    protected $description = 'Run a benchmark multiple times and report variance (min/max/avg) with change deltas';

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

        $benchmarkName = $this->argument('benchmark');
        $totalRuns = $runs + 1;
        $quiet = $this->option('json') || $this->option('ci');
        $commandStart = microtime(true);
        $runDurations = [];

        if (! $quiet) {
            $bar = $this->output->createProgressBar($totalRuns);
            $bar->setFormat(' %current%/%max% [%bar%] %message%');
            $bar->setMessage('Snapshot...');
            $bar->start();
        }

        // Step 1: Snapshot run.
        $t = microtime(true);
        $snapshotResult = $this->runBenchmarkInProcess($benchmarkName);
        $runDurations[] = microtime(true) - $t;

        $this->saveSnapshot([$benchmarkName => $snapshotResult]);

        if (! $quiet) {
            $bar->advance();
            $bar->setMessage('Benchmarking...');
        }

        // Step 2: Benchmark runs.
        $allRuns = [];

        for ($i = 0; $i < $runs; $i++) {
            $t = microtime(true);
            $allRuns[] = $this->runBenchmarkInProcess($benchmarkName);
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

        // Step 3: Display variance report.
        if ($this->option('json')) {
            $this->outputJson($snapshotResult, $allRuns, $avgRunDuration, $totalDuration);
        } elseif ($this->option('ci')) {
            $this->outputVarianceMarkdown($snapshotResult, $allRuns, $avgRunDuration, $totalDuration);
        } else {
            $this->displayVarianceResults($snapshotResult, $allRuns, $avgRunDuration, $totalDuration);
        }

        return Command::SUCCESS;
    }

    protected function runBenchmarkInProcess(string $name): array
    {
        $result = Process::path(base_path())
            ->timeout(300)
            ->run([
                PHP_BINARY, 'artisan', 'benchmark', $name,
                '--json',
                '--iterations='.$this->iterations,
                '--rounds='.$this->rounds,
                '--warmup='.$this->warmupRounds,
                '--attempts='.$this->option('attempts'),
            ]);

        if (! $result->successful()) {
            throw new \RuntimeException(
                "Benchmark process failed for '{$name}': ".$result->errorOutput()
            );
        }

        $data = json_decode($result->output(), true);

        if (! $data || ! isset($data['benchmarks'][$name])) {
            throw new \RuntimeException(
                "Invalid benchmark output for '{$name}': ".$result->output()
            );
        }

        return $data['benchmarks'][$name];
    }

    protected function displayVarianceResults(array $snapshot, array $allRuns, float $avgRunDuration, float $totalDuration): void
    {
        $snapshotImprovement = $this->improvement($snapshot);

        $bladeChanges = collect($allRuns)->map(fn ($run) => $this->percentChange($snapshot['blade_ms'], $run['blade_ms']));
        $blazeChanges = collect($allRuns)->map(fn ($run) => $this->percentChange($snapshot['blaze_ms'], $run['blaze_ms']));
        $improvementChanges = collect($allRuns)->map(fn ($run) => round($this->improvement($run) - $snapshotImprovement, 1));

        $headers = ['', 'Blade', 'Blaze', 'Improvement'];
        $rows = [
            [
                'Snapshot',
                $this->formatTime($snapshot['blade_ms']),
                $this->formatTime($snapshot['blaze_ms']),
                $snapshotImprovement.'%',
            ],
            [
                'Variance',
                $this->formatVarianceRange($bladeChanges->min(), $bladeChanges->max()),
                $this->formatVarianceRange($blazeChanges->min(), $blazeChanges->max()),
                $this->formatVarianceRange($improvementChanges->min(), $improvementChanges->max()),
            ],
            [
                'Std Dev',
                '±'.$this->stddev($bladeChanges).'%',
                '±'.$this->stddev($blazeChanges).'%',
                '±'.$this->stddev($improvementChanges).'%',
            ],
        ];

        $this->newLine(2);
        $this->table($headers, $rows);

        $this->newLine();
        $this->comment(
            count($allRuns)." runs x {$this->rounds} rounds x {$this->iterations} iterations"
            .", ~{$avgRunDuration}s/run, {$totalDuration}s total"
        );
    }

    protected function outputVarianceMarkdown(array $snapshot, array $allRuns, float $avgRunDuration, float $totalDuration): void
    {
        $snapshotImprovement = $this->improvement($snapshot);

        $bladeChanges = collect($allRuns)->map(fn ($run) => $this->percentChange($snapshot['blade_ms'], $run['blade_ms']));
        $blazeChanges = collect($allRuns)->map(fn ($run) => $this->percentChange($snapshot['blaze_ms'], $run['blaze_ms']));
        $improvementChanges = collect($allRuns)->map(fn ($run) => round($this->improvement($run) - $snapshotImprovement, 1));

        $headers = ['', 'Blade', 'Blaze', 'Improvement'];
        $rows = [
            [
                'Snapshot',
                $this->formatTime($snapshot['blade_ms']),
                $this->formatTime($snapshot['blaze_ms']),
                $snapshotImprovement.'%',
            ],
            [
                'Variance',
                $this->formatVarianceRange($bladeChanges->min(), $bladeChanges->max()),
                $this->formatVarianceRange($blazeChanges->min(), $blazeChanges->max()),
                $this->formatVarianceRange($improvementChanges->min(), $improvementChanges->max()),
            ],
            [
                'Std Dev',
                '±'.$this->stddev($bladeChanges).'%',
                '±'.$this->stddev($blazeChanges).'%',
                '±'.$this->stddev($improvementChanges).'%',
            ],
        ];

        $allRows = collect([$headers, ...$rows]);
        $widths = collect($headers)->keys()->map(
            fn ($i) => $allRows->max(fn ($row) => mb_strlen($row[$i]))
        );

        $formatRow = fn ($cells) => '| '.collect($cells)
            ->map(fn ($cell, $i) => Str::padRight($cell, $widths[$i]))
            ->implode(' | ').' |';

        $separator = '| '.$widths->map(fn ($w) => str_repeat('-', $w))->implode(' | ').' |';

        $md = collect([
            '## Benchmark Results',
            '',
            $formatRow($headers),
            $separator,
            ...collect($rows)->map($formatRow),
            '',
            '<sub>'.count($allRuns)." runs x {$this->rounds} rounds x {$this->iterations} iterations"
                .", ~{$avgRunDuration}s/run, {$totalDuration}s total"
                .'</sub>',
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
        $snapshotImprovement = $this->improvement($snapshot);

        $bladeChanges = collect($allRuns)->map(fn ($run) => $this->percentChange($snapshot['blade_ms'], $run['blade_ms']));
        $blazeChanges = collect($allRuns)->map(fn ($run) => $this->percentChange($snapshot['blaze_ms'], $run['blaze_ms']));
        $improvementChanges = collect($allRuns)->map(fn ($run) => round($this->improvement($run) - $snapshotImprovement, 1));

        $this->output->writeln(json_encode([
            'iterations' => $this->iterations,
            'rounds' => $this->rounds,
            'runs' => count($allRuns),
            'avg_run_duration_s' => $avgRunDuration,
            'total_duration_s' => $totalDuration,
            'snapshot' => [
                'blade_ms' => $snapshot['blade_ms'],
                'blaze_ms' => $snapshot['blaze_ms'],
                'improvement' => $snapshotImprovement,
            ],
            'variance' => [
                'blade' => ['min' => $bladeChanges->min(), 'max' => $bladeChanges->max(), 'stddev' => $this->stddev($bladeChanges)],
                'blaze' => ['min' => $blazeChanges->min(), 'max' => $blazeChanges->max(), 'stddev' => $this->stddev($blazeChanges)],
                'improvement' => ['min' => $improvementChanges->min(), 'max' => $improvementChanges->max(), 'stddev' => $this->stddev($improvementChanges)],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    protected function stddev(\Illuminate\Support\Collection $values): float
    {
        $count = $values->count();

        if ($count < 2) {
            return 0.0;
        }

        $mean = $values->avg();
        $sumSquares = $values->reduce(fn ($carry, $v) => $carry + ($v - $mean) ** 2, 0);

        return round(sqrt($sumSquares / ($count - 1)), 2);
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
