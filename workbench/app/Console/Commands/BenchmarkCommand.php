<?php

namespace Workbench\App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Benchmark;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

class BenchmarkCommand extends Command
{
    protected $signature = 'benchmark
        {benchmark : Name of the benchmark to run}
        {--iterations=5000 : Number of component renders per benchmark}
        {--rounds=100 : Number of timed rounds per benchmark}
        {--warmup=2 : Number of untimed warmup rounds}
        {--attempts=5 : Number of times to run the entire benchmark in separate processes}
        {--snapshot : Save results as the baseline snapshot}
        {--json : Output results as JSON}
        {--ci : Output a markdown table with no progress (for CI)}';

    protected $description = 'Run a Blaze performance benchmark';

    protected int $iterations;

    protected int $rounds;

    protected int $warmupRounds;

    protected int $filteredRounds = 0;

    public function handle(): int
    {
        $this->iterations = (int) $this->option('iterations');
        $this->rounds = (int) $this->option('rounds');
        $this->warmupRounds = (int) $this->option('warmup');
        $attempts = (int) $this->option('attempts');

        if ($attempts < 1) {
            $this->error('--attempts must be at least 1.');

            return Command::FAILURE;
        }

        if ($attempts > 1) {
            return $this->runMultipleAttempts($attempts);
        }

        $commandStart = microtime(true);

        Artisan::call('view:clear');

        $result = $this->runBenchmark();
        $totalDuration = round(microtime(true) - $commandStart, 2);

        $benchmarkName = $this->argument('benchmark');
        $results = [$benchmarkName => $result];

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

    protected function runBenchmark(): array
    {
        $benchmarkName = $this->argument('benchmark');
        $bladeView = "bench.blade.{$benchmarkName}";
        $blazeView = "bench.blaze.{$benchmarkName}";
        $showProgress = ! $this->option('ci') && ! $this->option('json');

        if ($showProgress) {
            $this->info("Running '{$benchmarkName}' ({$this->iterations} iterations x {$this->rounds} rounds)...");
            $this->newLine();
        }

        $totalSteps = $this->warmupRounds + $this->rounds;
        $bar = $showProgress ? $this->output->createProgressBar($totalSteps) : null;
        $bar?->setFormat(' %current%/%max% [%bar%] %message%');
        $bar?->setMessage('Warming up...');
        $bar?->start();

        // Warmup: compile views and stabilize opcache.
        for ($w = 0; $w < $this->warmupRounds; $w++) {
            $this->measureView($bladeView);
            $this->measureView($blazeView);
            $bar?->advance();
        }

        $bladeTimes = [];
        $blazeTimes = [];

        $bar?->setMessage('Benchmarking...');

        for ($r = 0; $r < $this->rounds; $r++) {
            $bladeTimes[] = $this->measureView($bladeView);
            $blazeTimes[] = $this->measureView($blazeView);
            $bar?->advance();
        }

        $bar?->setMessage('Done!');
        $bar?->finish();

        if ($showProgress) {
            $this->newLine(2);
        }

        $roundTotals = collect(range(0, $this->rounds - 1))->map(
            fn ($r) => $bladeTimes[$r] + $blazeTimes[$r]
        );

        $keptRounds = $this->nonOutlierIndices($roundTotals);
        $this->filteredRounds = $this->rounds - $keptRounds->count();

        $bladeTimes = $keptRounds->map(fn ($r) => $bladeTimes[$r])->all();
        $blazeTimes = $keptRounds->map(fn ($r) => $blazeTimes[$r])->all();

        return [
            'blade_ms' => round(collect($bladeTimes)->median(), 2),
            'blaze_ms' => round(collect($blazeTimes)->median(), 2),
        ];
    }

    protected function runMultipleAttempts(int $attempts): int
    {
        $benchmarkName = $this->argument('benchmark');
        $showProgress = ! $this->option('ci') && ! $this->option('json');
        $commandStart = microtime(true);

        $allAttempts = [];

        if ($showProgress) {
            $this->info("Running '{$benchmarkName}' ({$attempts} attempts, {$this->iterations} iterations x {$this->rounds} rounds each)...");
            $this->newLine();
            $bar = $this->output->createProgressBar($attempts);
            $bar->setFormat(' %current%/%max% [%bar%] %message%');
        }

        for ($i = 0; $i < $attempts; $i++) {
            if ($showProgress) {
                $bar->setMessage('Attempt '.($i + 1).'/'.$attempts.'...');
                $i === 0 ? $bar->start() : $bar->display();
            }

            $result = Process::path(base_path())
                ->timeout(300)
                ->run([
                    PHP_BINARY, 'artisan', 'benchmark', $benchmarkName,
                    '--attempts=1',
                    '--json',
                    '--iterations='.$this->iterations,
                    '--rounds='.$this->rounds,
                    '--warmup='.$this->warmupRounds,
                ]);

            if (! $result->successful()) {
                if ($showProgress) {
                    $this->newLine();
                }

                $this->error('Attempt '.($i + 1).' failed: '.$result->errorOutput());

                return Command::FAILURE;
            }

            $data = json_decode($result->output(), true);

            if (! $data || ! isset($data['benchmarks'][$benchmarkName])) {
                if ($showProgress) {
                    $this->newLine();
                }

                $this->error('Invalid output from attempt '.($i + 1).': '.$result->output());

                return Command::FAILURE;
            }

            $allAttempts[] = $data['benchmarks'][$benchmarkName];

            if ($showProgress) {
                $bar->advance();
            }
        }

        if ($showProgress) {
            $bar->setMessage('Done!');
            $bar->finish();
            $this->newLine(2);
        }

        $totalDuration = round(microtime(true) - $commandStart, 2);

        // Filter outlier attempts — if either blade or blaze is an outlier, drop the whole attempt.
        $bladeValues = collect($allAttempts)->map(fn ($r) => $r['blade_ms']);
        $blazeValues = collect($allAttempts)->map(fn ($r) => $r['blaze_ms']);
        $keptIndices = $this->nonOutlierIndices($bladeValues)
            ->intersect($this->nonOutlierIndices($blazeValues))
            ->values();

        $medianResult = [
            'blade_ms' => round($keptIndices->map(fn ($i) => $allAttempts[$i]['blade_ms'])->median(), 2),
            'blaze_ms' => round($keptIndices->map(fn ($i) => $allAttempts[$i]['blaze_ms'])->median(), 2),
        ];

        $results = [$benchmarkName => $medianResult];

        if ($this->option('json')) {
            $this->outputJsonAttemptsResults($benchmarkName, $allAttempts, $results, $keptIndices, $totalDuration);
        } elseif ($this->option('ci')) {
            $this->outputMarkdownAttempts($allAttempts, $results, $keptIndices, $totalDuration);
        } else {
            $this->displayAttemptsResults($allAttempts, $results, $keptIndices, $totalDuration);
        }

        if ($this->option('snapshot')) {
            $this->saveSnapshot($results);
        }

        return Command::SUCCESS;
    }

    // ──────────────────────────────────────────────────────────────
    //  Display — single attempt
    // ──────────────────────────────────────────────────────────────

    protected function buildTable(array $results): array
    {
        $snapshot = $this->option('snapshot') ? null : $this->loadSnapshot();

        $headers = ['Benchmark', 'Blade', 'Blaze', 'Improvement'];

        $rows = collect($results)->map(function ($result, $name) use ($snapshot) {
            $blade = $this->formatTime($result['blade_ms']);
            $blaze = $this->formatTime($result['blaze_ms']);
            $improvement = $this->improvement($result).'%';

            if ($prev = $snapshot['benchmarks'][$name] ?? null) {
                $blade .= ' '.$this->formatChange($prev['blade_ms'], $result['blade_ms'], 1);
                $blaze .= ' '.$this->formatChange($prev['blaze_ms'], $result['blaze_ms'], 1);
                $improvement .= ' '.$this->formatImprovementChange($prev['improvement'], $this->improvement($result));
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
        $this->info("{$this->iterations} iterations x {$this->rounds} rounds, {$totalDuration}s total");
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
            '<sub>'."{$this->iterations} iterations x {$this->rounds} rounds, {$totalDuration}s total"
                ." &mdash; {$this->filteredRounds} outlier rounds excluded (IQR)"
                .($snapshot ? ' &mdash; compared against baseline snapshot' : '')
                .'</sub>',
        ])->implode("\n");

        $this->output->writeln($md);
    }

    protected function outputJsonResults(array $results, float $totalDuration): void
    {
        $this->output->writeln(json_encode([
            'iterations' => $this->iterations,
            'rounds' => $this->rounds,
            'filtered_rounds' => $this->filteredRounds,
            'total_duration_s' => $totalDuration,
            'benchmarks' => $results,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    // ──────────────────────────────────────────────────────────────
    //  Display — multiple attempts
    // ──────────────────────────────────────────────────────────────

    protected function displayAttemptsResults(array $allAttempts, array $results, Collection $keptIndices, float $totalDuration): void
    {
        $attempts = count($allAttempts);
        $filteredAttempts = $attempts - $keptIndices->count();

        foreach ($allAttempts as $i => $attempt) {
            $isOutlier = ! $keptIndices->contains($i);
            $improvement = $this->improvement($attempt);
            $line = sprintf(
                '  Attempt %d:  Blade %s  Blaze %s  (%s%%)',
                $i + 1,
                $this->formatTime($attempt['blade_ms']),
                $this->formatTime($attempt['blaze_ms']),
                $improvement
            );

            $isOutlier
                ? $this->line($line.'  <fg=yellow>← outlier</>')
                : $this->line($line);
        }

        [$headers, $rows, $snapshot] = $this->buildTable($results);

        $this->newLine();
        $this->table($headers, $rows);

        $this->newLine();
        $this->info(
            "Median of {$attempts} attempts"
            .($filteredAttempts ? " ({$filteredAttempts} outlier(s) excluded)" : '')
            .", {$this->iterations} iterations x {$this->rounds} rounds, {$totalDuration}s total"
        );

        if ($snapshot) {
            $rounds = $snapshot['rounds'] ?? 1;
            $this->comment("Compared against baseline snapshot ({$snapshot['iterations']} iterations x {$rounds} rounds)");
        }
    }

    protected function outputMarkdownAttempts(array $allAttempts, array $results, Collection $keptIndices, float $totalDuration): void
    {
        $attempts = count($allAttempts);
        $filteredAttempts = $attempts - $keptIndices->count();

        [$headers, $rows, $snapshot] = $this->buildTable($results);

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
            '<sub>'
                ."Median of {$attempts} attempts"
                .($filteredAttempts ? " ({$filteredAttempts} outlier(s) excluded)" : '')
                .", {$this->iterations} iterations x {$this->rounds} rounds, {$totalDuration}s total"
                .($snapshot ? ' &mdash; compared against baseline snapshot' : '')
                .'</sub>',
        ])->implode("\n");

        $this->output->writeln($md);
    }

    protected function outputJsonAttemptsResults(string $benchmarkName, array $allAttempts, array $results, Collection $keptIndices, float $totalDuration): void
    {
        $attempts = count($allAttempts);

        $this->output->writeln(json_encode([
            'iterations' => $this->iterations,
            'rounds' => $this->rounds,
            'attempts' => $attempts,
            'filtered_attempts' => $attempts - $keptIndices->count(),
            'total_duration_s' => $totalDuration,
            'attempts_detail' => collect($allAttempts)->map(fn ($attempt, $i) => [
                'blade_ms' => $attempt['blade_ms'],
                'blaze_ms' => $attempt['blaze_ms'],
                'improvement' => $this->improvement($attempt),
                'outlier' => ! $keptIndices->contains($i),
            ])->values()->all(),
            'benchmarks' => $results,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    // ──────────────────────────────────────────────────────────────
    //  Snapshot
    // ──────────────────────────────────────────────────────────────

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

        File::put($path, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

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
        return dirname(__DIR__, 4).'/benchmark-snapshot.json';
    }

    // ──────────────────────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────────────────────

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

        return "({$sign}".round($change, 1).'%)';
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

    /**
     * Return the indices of non-outlier values using the IQR method.
     *
     * Values below Q1 - 1.5*IQR or above Q3 + 1.5*IQR are considered outliers.
     */
    protected function nonOutlierIndices(Collection $values): Collection
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
        return number_format($ms, 2).'ms';
    }

}
