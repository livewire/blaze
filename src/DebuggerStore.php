<?php

namespace Livewire\Blaze;

use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class DebuggerStore
{
    protected string $path;

    public function __construct()
    {
        $this->path = storage_path('blaze/traces');
    }

    /**
     * Store profiler trace data for this request.
     *
     * Each request writes to its own ULID-named file,
     * so concurrent requests never touch the same file.
     */
    public function storeTrace(array $data): string
    {
        $this->ensureDirectoryExists();

        $id = (string) Str::ulid();

        File::put($this->path.'/'.$id.'.json', json_encode($data, JSON_UNESCAPED_SLASHES));

        $this->autoPrune();

        return $id;
    }

    /**
     * Get the most recent profiler trace data.
     *
     * ULID filenames sort lexicographically by time,
     * so the last file alphabetically is the latest.
     */
    public function getLatestTrace(): ?array
    {
        if (! File::isDirectory($this->path)) {
            return null;
        }

        $files = File::glob($this->path.'/*.json');

        if (empty($files)) {
            return null;
        }

        sort($files);

        return json_decode(File::get(end($files)), true);
    }

    /**
     * Get a specific trace by its ID (ULID filename without extension).
     */
    public function getTrace(string $id): ?array
    {
        $file = $this->path.'/'.$id.'.json';

        if (! File::exists($file)) {
            return null;
        }

        return json_decode(File::get($file), true);
    }

    /**
     * List recent traces with summary metadata.
     *
     * Returns newest-first, up to $max entries.
     */
    public function listTraces(int $max = 20): array
    {
        if (! File::isDirectory($this->path)) {
            return [];
        }

        $files = File::glob($this->path.'/*.json');

        if (empty($files)) {
            return [];
        }

        // ULID filenames sort chronologically — take the most recent $max.
        sort($files);
        $files = array_reverse(array_slice($files, -$max));

        $traces = [];

        foreach ($files as $file) {
            $contents = json_decode(File::get($file), true);

            if ($contents === null) {
                continue;
            }

            $traces[] = [
                'id' => pathinfo($file, PATHINFO_FILENAME),
                'url' => $contents['url'] ?? null,
                'mode' => $contents['mode'] ?? null,
                'timestamp' => $contents['timestamp'] ?? null,
                'renderTime' => $contents['renderTime'] ?? null,
            ];
        }

        return $traces;
    }

    /**
     * Delete trace files older than the given number of hours.
     */
    public function prune(int $hours = 24): void
    {
        if (! File::isDirectory($this->path)) {
            return;
        }

        $cutoff = Carbon::now()->subHours($hours)->getTimestamp();

        foreach (File::files($this->path) as $file) {
            if ($file->getExtension() !== 'json') {
                continue;
            }

            if ($file->getMTime() < $cutoff) {
                File::delete($file->getPathname());
            }
        }
    }

    /**
     * Delete all stored trace files.
     */
    public function clear(): void
    {
        if (! File::isDirectory($this->path)) {
            return;
        }

        File::delete(File::glob($this->path.'/*.json'));
    }

    /**
     * Auto-prune old trace files with a 5% probability.
     */
    protected function autoPrune(): void
    {
        if (rand(1, 100) <= 5) {
            $this->prune();
        }
    }

    /**
     * Ensure the storage directory exists with a .gitignore file.
     */
    protected function ensureDirectoryExists(): void
    {
        if (File::isDirectory($this->path)) {
            return;
        }

        File::makeDirectory($this->path, 0755, true);
        File::put($this->path.'/.gitignore', "*.json\n");
    }
}
