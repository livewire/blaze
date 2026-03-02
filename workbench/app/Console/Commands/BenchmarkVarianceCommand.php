<?php

namespace Workbench\App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class BenchmarkVarianceCommand extends BenchmarkCommand
{
    protected $signature = 'benchmark:variance
        {--runs=5 : Number of benchmark runs after the initial snapshot run}
        {--iterations=2500 : Number of component renders per benchmark}
        {--rounds=100 : Number of timed rounds per benchmark}
        {--warmup=2 : Number of untimed warmup rounds}
        {--filter-outliers : Exclude outlier rounds using the IQR method}
        {--ci : Suppress progress bars}';

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

        Artisan::call('view:clear');

        // Step 1: Snapshot run
        $this->info("Run 1/{$totalRuns}: Creating baseline snapshot...");
        $snapshotResults = $this->runBenchmarks();
        $this->saveSnapshot($snapshotResults);

        // Step 2: Benchmark runs
        $allRuns = [];

        for ($i = 0; $i < $runs; $i++) {
            $this->newLine();
            $this->info('Run '.($i + 2)."/{$totalRuns}: Benchmarking...");
            Artisan::call('view:clear');
            $allRuns[] = $this->runBenchmarks();
        }

        // Step 3: Display variance report
        $this->displayVarianceResults($snapshotResults, $allRuns);

        return Command::SUCCESS;
    }

    protected function displayVarianceResults(array $snapshot, array $allRuns): void
    {
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
        }

        $this->newLine(2);
        $this->table($headers, $rows);

        $this->newLine();
        $this->comment(
            "{$this->iterations} iterations x {$this->rounds} rounds per benchmark, "
            .count($allRuns).' runs'
            .($this->option('filter-outliers') ? ' (outliers excluded)' : '')
        );
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
