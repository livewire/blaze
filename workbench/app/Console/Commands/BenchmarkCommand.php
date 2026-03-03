<?php

namespace Workbench\App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Benchmark;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

class BenchmarkCommand extends Command
{
    protected $signature = 'benchmark
        {--iterations=5000 : Number of component renders per benchmark}
        {--rounds=100 : Number of timed rounds per benchmark}
        {--warmup=2 : Number of untimed warmup rounds}
        {--snapshot : Save results as the baseline snapshot}
        {--json : Output results as JSON}
        {--only= : Run only the named benchmark}
        {--processes= : Number of parallel processes (auto-detected if omitted)}
        {--ci : Output a markdown table with no progress (for CI)}';

    protected $description = 'Run Blaze performance benchmarks';

    protected int $iterations;

    protected int $rounds;

    protected int $warmupRounds;

    protected int $filteredRounds = 0;

    protected int $processes = 1;

    public function handle(): int
    {
        $this->iterations = (int) $this->option('iterations');
        $this->rounds = (int) $this->option('rounds');
        $this->warmupRounds = (int) $this->option('warmup');
        $this->processes = $this->detectProcessCount();
        $commandStart = microtime(true);

        Artisan::call('view:clear');

        $results = $this->runBenchmarks();
        $totalDuration = round(microtime(true) - $commandStart, 2);

        if ($this->option('json')) {
            $this->outputJsonResults($results, $totalDuration);
        } elseif ($this->option('ci')) {
            $this->outputMarkdown($results, $totalDuration);
        } else {
            $this->displayResults($results, $totalDuration);
        }

        if ($this->option('snapshot')) {
            $this->saveSnapshot($results);
        }

        return Command::SUCCESS;
    }

    protected function runBenchmarks(): array
    {
        $showProgress = ! $this->option('ci') && ! $this->option('json');
        $benchmarks = $this->getFilteredBenchmarks();
        $names = array_keys($benchmarks);

        if ($showProgress) {
            $parallel = $this->processes > 1 ? " across {$this->processes} processes" : '';
            $this->info("Running benchmarks ({$this->iterations} iterations x {$this->rounds} rounds{$parallel})...");
            $this->newLine();
        }

        $warmupSteps = count($benchmarks) * $this->warmupRounds;
        $benchmarkSteps = $this->processes > 1 ? 0 : ($this->rounds * count($benchmarks));
        $bar = $showProgress ? $this->output->createProgressBar($warmupSteps + $benchmarkSteps) : null;
        $bar?->setFormat(' %current%/%max% [%bar%] %message%');
        $bar?->setMessage('Warming up...');
        $bar?->start();

        // Warmup: compile views and stabilize opcache.
        foreach ($benchmarks as $benchmark) {
            for ($w = 0; $w < $this->warmupRounds; $w++) {
                $this->measureView($benchmark['blade']);
                $this->measureView($benchmark['blaze']);
                $bar?->advance();
            }
        }

        if ($this->processes > 1) {
            $bar?->setMessage('Done!');
            $bar?->finish();

            if ($showProgress) {
                $this->newLine(2);
                $this->comment("Forking {$this->processes} worker processes...");
            }

            [$bladeTimes, $blazeTimes] = $this->runRoundsParallel($benchmarks, $names);

            if ($showProgress) {
                $this->newLine();
            }
        } else {
            $bladeTimes = array_fill_keys($names, []);
            $blazeTimes = array_fill_keys($names, []);

            $bar?->setMessage('Benchmarking...');

            for ($r = 0; $r < $this->rounds; $r++) {
                foreach ($benchmarks as $name => $benchmark) {
                    $bladeTimes[$name][] = $this->measureView($benchmark['blade']);
                    $blazeTimes[$name][] = $this->measureView($benchmark['blaze']);
                    $bar?->advance();
                }
            }

            $bar?->setMessage('Done!');
            $bar?->finish();

            if ($showProgress) {
                $this->newLine(2);
            }
        }

        $roundTotals = collect(range(0, $this->rounds - 1))->map(
            fn ($r) => collect($names)->sum(fn ($name) => $bladeTimes[$name][$r] + $blazeTimes[$name][$r])
        );

        $keptRounds = $this->nonOutlierIndices($roundTotals);
        $this->filteredRounds = $this->rounds - $keptRounds->count();

        foreach ($names as $name) {
            $bladeTimes[$name] = $keptRounds->map(fn ($r) => $bladeTimes[$name][$r])->all();
            $blazeTimes[$name] = $keptRounds->map(fn ($r) => $blazeTimes[$name][$r])->all();
        }

        return collect($names)->mapWithKeys(fn ($name) => [
            $name => [
                'blade_ms' => round(collect($bladeTimes[$name])->median(), 2),
                'blaze_ms' => round(collect($blazeTimes[$name])->median(), 2),
            ],
        ])->all();
    }

    protected function runRoundsParallel(array $benchmarks, array $names): array
    {
        $roundsPerProcess = intdiv($this->rounds, $this->processes);
        $remainder = $this->rounds % $this->processes;
        $iterations = $this->iterations;

        $tasks = [];

        for ($p = 0; $p < $this->processes; $p++) {
            $workerRounds = $roundsPerProcess + ($p < $remainder ? 1 : 0);

            if ($workerRounds === 0) {
                continue;
            }

            $tasks[] = function () use ($benchmarks, $names, $workerRounds, $iterations) {
                $bladeTimes = array_fill_keys($names, []);
                $blazeTimes = array_fill_keys($names, []);

                for ($r = 0; $r < $workerRounds; $r++) {
                    foreach ($benchmarks as $name => $benchmark) {
                        $bladeTimes[$name][] = Benchmark::measure(
                            fn () => View::make($benchmark['blade'], ['iterations' => $iterations])->render()
                        );
                        $blazeTimes[$name][] = Benchmark::measure(
                            fn () => View::make($benchmark['blaze'], ['iterations' => $iterations])->render()
                        );
                    }
                }

                return compact('bladeTimes', 'blazeTimes');
            };
        }

        $workerResults = Concurrency::driver('fork')->run($tasks);

        // Merge timing data from all workers.
        $bladeTimes = array_fill_keys($names, []);
        $blazeTimes = array_fill_keys($names, []);

        foreach ($workerResults as $result) {
            foreach ($names as $name) {
                array_push($bladeTimes[$name], ...$result['bladeTimes'][$name]);
                array_push($blazeTimes[$name], ...$result['blazeTimes'][$name]);
            }
        }

        return [$bladeTimes, $blazeTimes];
    }

    protected function detectProcessCount(): int
    {
        if ($this->option('processes')) {
            return max(1, (int) $this->option('processes'));
        }

        if (! function_exists('pcntl_fork')) {
            return 1;
        }

        $cores = match (PHP_OS_FAMILY) {
            'Darwin' => (int) trim((string) shell_exec('sysctl -n hw.ncpu')),
            'Linux' => (int) trim((string) shell_exec('nproc')),
            default => 1,
        };

        return max(1, min($cores, $this->rounds));
    }

    protected function buildTable(array $results): array
    {
        $snapshot = $this->option('snapshot') ? null : $this->loadSnapshot();

        $headers = ['Benchmark', 'Blade', 'Blaze', 'Improvement'];

        $rows = collect($results)->map(function ($result, $name) use ($snapshot) {
            $blade = $this->formatTime($result['blade_ms']);
            $blaze = $this->formatTime($result['blaze_ms']);
            $improvement = $this->improvement($result) . '%';

            if ($prev = $snapshot['benchmarks'][$name] ?? null) {
                $blade .= ' ' . $this->formatChange($prev['blade_ms'], $result['blade_ms'], 1);
                $blaze .= ' ' . $this->formatChange($prev['blaze_ms'], $result['blaze_ms'], 1);
                $improvement .= ' ' . $this->formatImprovementChange($prev['improvement'], $this->improvement($result));
            }

            return [$name, $blade, $blaze, $improvement];
        })->values()->all();

        return [$headers, $rows, $snapshot];
    }

    protected function displayResults(array $results, float $totalDuration): void
    {
        [$headers, $rows, $snapshot] = $this->buildTable($results);

        $this->newLine();
        $this->table($headers, $rows);

        $this->newLine();
        $parallel = $this->processes > 1 ? " across {$this->processes} processes" : '';
        $this->info("{$this->iterations} iterations x {$this->rounds} rounds per benchmark{$parallel}, {$totalDuration}s total");
        $this->comment("{$this->filteredRounds} outlier rounds excluded (IQR method)");

        if ($snapshot) {
            $rounds = $snapshot['rounds'] ?? 1;
            $this->comment("Compared against baseline snapshot ({$snapshot['iterations']} iterations x {$rounds} rounds)");
        }
    }

    protected function outputMarkdown(array $results, float $totalDuration): void
    {
        [$headers, $rows, $snapshot] = $this->buildTable($results);

        $allRows = collect([$headers, ...$rows]);
        $widths = collect($headers)->keys()->map(
            fn ($i) => $allRows->max(fn ($row) => mb_strlen($row[$i]))
        );

        $formatRow = fn ($cells) => '| ' . collect($cells)
            ->map(fn ($cell, $i) => Str::padRight($cell, $widths[$i]))
            ->implode(' | ') . ' |';

        $separator = '| ' . $widths->map(fn ($w) => str_repeat('-', $w))->implode(' | ') . ' |';

        $md = collect([
            '## Benchmark Results',
            '',
            $formatRow($headers),
            $separator,
            ...collect($rows)->map($formatRow),
            '',
            '<sub>' . "{$this->iterations} iterations x {$this->rounds} rounds per benchmark"
                . ($this->processes > 1 ? " across {$this->processes} processes" : '')
                . ", {$totalDuration}s total"
                . " &mdash; {$this->filteredRounds} outlier rounds excluded (IQR)"
                . ($snapshot ? ' &mdash; compared against baseline snapshot' : '')
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

        $path = $this->snapshotPath();

        File::put($path, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

        $this->newLine();
        $this->info("Snapshot saved to {$path}");
    }

    protected function loadSnapshot(): ?array
    {
        $path = $this->snapshotPath();

        if (! File::exists($path)) {
            return null;
        }

        $data = File::json($path);

        return is_array($data) && isset($data['benchmarks']) ? $data : null;
    }

    protected function snapshotPath(): string
    {
        return dirname(__DIR__, 4) . '/benchmark-snapshot.json';
    }

    protected function improvement(array $result): float
    {
        return $result['blade_ms'] > 0
            ? round((1 - $result['blaze_ms'] / $result['blade_ms']) * 100, 1)
            : 0;
    }

    protected function formatChange(float $old, float $new, float $threshold = 0.1): string
    {
        if ($old == 0) {
            return '(~)';
        }

        $change = ($new - $old) / abs($old) * 100;

        if (abs($change) < $threshold) {
            return '(~)';
        }

        $sign = $change > 0 ? '+' : '';

        return "({$sign}" . round($change, 1) . '%)';
    }

    protected function formatImprovementChange(float $old, float $new, float $threshold = 0.1): string
    {
        $delta = round($new - $old, 1);

        if (abs($delta) < $threshold) {
            return '(~)';
        }

        $sign = $delta > 0 ? '+' : '';

        return "({$sign}{$delta}%)";
    }

    protected function getFilteredBenchmarks(): array
    {
        $benchmarks = $this->getBenchmarks();

        if ($this->input->hasOption('only') && ($only = $this->option('only'))) {
            if (! isset($benchmarks[$only])) {
                throw new \InvalidArgumentException("Unknown benchmark: {$only}");
            }

            return [$only => $benchmarks[$only]];
        }

        return $benchmarks;
    }

    protected function outputJsonResults(array $results, float $totalDuration): void
    {
        $this->output->writeln(json_encode([
            'iterations' => $this->iterations,
            'rounds' => $this->rounds,
            'processes' => $this->processes,
            'filtered_rounds' => $this->filteredRounds,
            'total_duration_s' => $totalDuration,
            'benchmarks' => $results,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    protected function getBenchmarks(): array
    {
        return [
            // 'No attributes' => [
            //     'blade' => 'bench.blade.no-attributes',
            //     'blaze' => 'bench.blaze.no-attributes',
            // ],
            // 'Attributes only' => [
            //     'blade' => 'bench.blade.attributes',
            //     'blaze' => 'bench.blaze.attributes',
            // ],
            // 'Attributes + merge()' => [
            //     'blade' => 'bench.blade.merge',
            //     'blaze' => 'bench.blaze.merge',
            // ],
            // 'Attributes + class()' => [
            //     'blade' => 'bench.blade.class',
            //     'blaze' => 'bench.blaze.class',
            // ],
            'Props + attributes' => [
                'blade' => 'bench.blade.props',
                'blaze' => 'bench.blaze.props',
            ],
            // 'Default slot' => [
            //     'blade' => 'bench.blade.slot',
            //     'blaze' => 'bench.blaze.slot',
            // ],
            // 'Named slots' => [
            //     'blade' => 'bench.blade.named-slots',
            //     'blaze' => 'bench.blaze.named-slots',
            // ],
            // '`@aware` (nested)' => [
            //     'blade' => 'bench.blade.aware',
            //     'blaze' => 'bench.blaze.aware',
            // ],
            // 'Attribute forwarding' => [
            //     'blade' => 'bench.blade.forwarding',
            //     'blaze' => 'bench.blaze.forwarding',
            // ],
        ];
    }

    /**
     * Return the indices of non-outlier values using the IQR method.
     *
     * Values below Q1 - 1.5*IQR or above Q3 + 1.5*IQR are considered outliers.
     */
    protected function nonOutlierIndices(\Illuminate\Support\Collection $values): \Illuminate\Support\Collection
    {
        if ($values->count() < 4) {
            return $values->keys();
        }

        $sorted = $values->sort()->values();
        $count = $sorted->count();

        $q1 = $sorted[intdiv($count, 4)];
        $q3 = $sorted[intdiv($count * 3, 4)];
        $iqr = $q3 - $q1;

        $lower = $q1 - 1.5 * $iqr;
        $upper = $q3 + 1.5 * $iqr;

        return $values->keys()->filter(fn ($i) => $values[$i] >= $lower && $values[$i] <= $upper)->values();
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
}
