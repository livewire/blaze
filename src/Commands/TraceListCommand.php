<?php

namespace Livewire\Blaze\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Livewire\Blaze\DebuggerStore;

class TraceListCommand extends Command
{
    protected $signature = 'blaze:trace:list
                            {--limit=30 : Maximum number of traces to display}';

    protected $description = 'List recorded Blaze profiler traces';

    public function handle(DebuggerStore $store): int
    {
        $traces = $store->listTraces((int) $this->option('limit'));

        if (empty($traces)) {
            $this->components->warn('No traces recorded yet. Visit your app with the debugger enabled to start recording.');

            return self::SUCCESS;
        }

        $this->newLine();

        $this->table(
            ['ID', 'URL', 'Mode', 'Render Time', 'Recorded'],
            collect($traces)->map(fn (array $trace) => [
                $trace['id'],
                $trace['url'] ?? '-',
                $trace['mode'] ?? '-',
                $this->formatMs($trace['renderTime'] ?? 0),
                $trace['timestamp'] ? Carbon::parse($trace['timestamp'])->diffForHumans(short: true) : '-',
            ])->all(),
        );

        $this->newLine();
        $this->line('  <fg=gray>Run</> <fg=white>php artisan blaze:trace:show [id]</> <fg=gray>to inspect a trace.</>');

        return self::SUCCESS;
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
