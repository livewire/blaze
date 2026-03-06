<?php

namespace Livewire\Blaze;

use Illuminate\Support\Facades\Cache;

class DebuggerStore
{
    /**
     * Store profiler trace data for the most recent request.
     */
    public function storeTrace(array $data): void
    {
        Cache::put('blaze_profiler_trace', $data, 300);
    }

    /**
     * Get the most recent profiler trace data.
     */
    public function getLatestTrace(): ?array
    {
        return Cache::get('blaze_profiler_trace');
    }
}
