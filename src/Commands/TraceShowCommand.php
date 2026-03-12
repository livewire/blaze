<?php

namespace Livewire\Blaze\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Livewire\Blaze\DebuggerStore;

class TraceShowCommand extends Command
{
    protected $signature = 'blaze:trace:show
                            {id : The trace ID to inspect}
                            {--limit=30 : Number of slowest components to display}';

    protected $description = 'Display details of a recorded Blaze profiler trace';

    public function handle(DebuggerStore $store): int
    {
        $id = $this->argument('id');
        $trace = $store->getTrace($id);

        if (! $trace) {
            $this->components->error("Trace [{$id}] not found.");

            return self::FAILURE;
        }

        $limit = (int) $this->option('limit');
        $components = $trace['components'] ?? [];
        $entries = $trace['entries'] ?? [];
        $totalComponents = array_sum(array_column($components, 'count'));
        $strategies = collect($entries)->countBy(fn (array $entry) => $entry['strategy'] ?? 'compiled')->all();

        $this->newLine();
        $this->table([], [
            ['ID', $id],
            ['URL', $trace['url'] ?? '-'],
            ['Mode', $trace['mode'] ?? '-'],
            ['Render Time', $this->formatMs($trace['renderTime'] ?? 0)],
            ['Components', $totalComponents],
            ['Strategies', $this->formatStrategies($strategies)],
            ['Date', ($trace['timestamp'] ?? null) ? Carbon::parse($trace['timestamp'])->toDayDateTimeString() : '-'],
        ]);

        if (! empty($components)) {
            $top = array_slice($components, 0, $limit);

            $strategiesByComponent = collect($entries)
                ->groupBy('name')
                ->map(fn ($group) => $group->countBy(fn ($e) => $e['strategy'] ?? 'compiled')->all())
                ->all();

            $this->newLine();
            $this->table(
                ['#', 'Component', 'Count', 'Strategies', 'Self Time'],
                collect($top)->map(fn (array $comp, int $i) => [
                    $i + 1,
                    $comp['name'],
                    $comp['count'],
                    $this->formatStrategies($strategiesByComponent[$comp['name']] ?? []),
                    $this->formatMs($comp['selfTime']),
                ])->all(),
            );
        }

        return self::SUCCESS;
    }

    protected function formatStrategies(array $strategies): string
    {
        $abbreviations = [
            'compiled' => 'c',
            'memo' => 'm',
            'blade' => 'b',
        ];

        return collect($strategies)
            ->map(fn (int $count, string $strategy) => $count . ($abbreviations[$strategy] ?? $strategy[0]))
            ->implode('/');
    }

    protected function formatMs(float $value): string
    {
        if ($value >= 1000) {
            return round($value / 1000, 2) . 's';
        }

        if ($value < 0.01 && $value > 0) {
            return round($value * 1000, 2) . 'us';
        }

        return round($value, 2) . 'ms';
    }
}
