<?php

namespace Workbench\App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Benchmark;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

class BenchmarkCommand extends Command
{
    protected $signature = 'benchmark
        {--iterations=10000 : Number of component renders per benchmark}
        {--rounds=10 : Number of timed rounds per benchmark}
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

        $this->option('ci')
            ? $this->outputMarkdown($results)
            : $this->displayResults($results);

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
            $this->info("Running benchmarks ({$this->iterations} iterations x {$this->rounds} rounds)...");
            $this->newLine();
        }

        $totalSteps = (count($benchmarks) * $this->warmupRounds) + ($this->rounds * count($benchmarks));
        $bar = $showProgress ? $this->output->createProgressBar($totalSteps) : null;
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

        return collect($names)->mapWithKeys(fn ($name) => [
            $name => [
                'blade_ms' => round(collect($bladeTimes[$name])->median(), 2),
                'blaze_ms' => round(collect($blazeTimes[$name])->median(), 2),
            ],
        ])->all();
    }

    // region Display

    protected function buildTable(array $results): array
    {
        $snapshot = $this->option('snapshot') ? null : $this->loadSnapshot();

        $headers = ['Benchmark', 'Blade', 'Blaze', 'Improvement'];

        $rows = collect($results)->map(function ($result, $name) use ($snapshot) {
            $blade = $this->formatTime($result['blade_ms']);
            $blaze = $this->formatTime($result['blaze_ms']);
            $improvement = $this->improvement($result) . '%';

            if ($prev = $snapshot['benchmarks'][$name] ?? null) {
                $blade .= ' ' . $this->formatChange($prev['blade_ms'], $result['blade_ms']);
                $blaze .= ' ' . $this->formatChange($prev['blaze_ms'], $result['blaze_ms']);
                $improvement .= ' ' . $this->formatChange($prev['improvement'], $this->improvement($result));
            }

            return [$name, $blade, $blaze, $improvement];
        })->values()->all();

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
                . ($snapshot ? ' &mdash; compared against committed snapshot' : '')
                . '</sub>',
        ])->implode("\n");

        $this->output->writeln($md);
    }

    // endregion

    // region Snapshot

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
        return $this->option('ci')
            ? base_path('.github/benchmark-snapshot.json')
            : base_path('benchmark-snapshot.json');
    }

    // endregion

    // region Helpers

    protected function improvement(array $result): float
    {
        return $result['blade_ms'] > 0
            ? round((1 - $result['blaze_ms'] / $result['blade_ms']) * 100, 1)
            : 0;
    }

    protected function formatChange(float $old, float $new, float $threshold = 5.0): string
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
