<?php

namespace Livewire\Blaze\Commands;

use Illuminate\Console\Command;
use Livewire\Blaze\DebuggerStore;

class TraceClearCommand extends Command
{
    protected $signature = 'blaze:trace:clear';

    protected $description = 'Delete all recorded Blaze profiler traces';

    public function handle(DebuggerStore $store): int
    {
        $store->clear();

        $this->components->info('All traces have been cleared.');

        return self::SUCCESS;
    }
}
