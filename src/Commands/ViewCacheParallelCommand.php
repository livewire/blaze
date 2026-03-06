<?php

namespace Livewire\Blaze\Commands;

use Illuminate\Console\Application;
use Illuminate\Console\Command;
use Illuminate\Foundation\Console\ViewCacheCommand as BaseCommand;
use Illuminate\Support\Facades\File;
use Illuminate\Process\Pool;
use Illuminate\Support\Collection;
use Symfony\Component\Finder\SplFileInfo;
use Illuminate\Process\Factory as ProcessFactory;

class ViewCacheParallelCommand extends BaseCommand
{
    protected $signature = 'view:cache {--processes : The number of processes to use for parallel compilation}';

    public function handle()
    {
        if (isset($_SERVER['VIEW_CACHE_FILES'])) {
            $views = File::lines($_SERVER['VIEW_CACHE_FILES']);

            $compiler = $this->laravel->make('view')->getEngineResolver()->resolve('blade')->getCompiler();

            foreach ($views as $view) {
                $compiler->compile($view);
            }

            return Command::SUCCESS;
        }

        $this->callSilent('view:clear');

        $views = $this->paths()
            ->flatMap(fn ($path) => $this->bladeFilesIn([$path]))
            ->map(fn (SplFileInfo $file) => $file->getRealPath())
            ->values();

        if ($views->isEmpty()) {
            $this->components->info('No Blade templates found.');

            return Command::SUCCESS;
        }

        $processes = (int) $this->option('processes') ?: $this->detectCpuCores();
        $shardedViews = $views->split(min($processes, $views->count()));

        $compiledPath = $this->laravel->make('config')->get('view.compiled');
        $shardDirectory = $compiledPath . '/blaze';

        File::ensureDirectoryExists($shardDirectory);

        $shards = $shardedViews->map(function (Collection $files, $i) use ($shardDirectory) {
            File::put($path = $shardDirectory . '/_views_' . $i, $files->join("\n"));

            return $path;
        });

        $results = $this->laravel->make(ProcessFactory::class)->concurrently(function (Pool $pool) use ($shards) {
            $shards->each(fn (string $path, int $i) => $pool
                ->as($i)
                ->path(base_path())
                ->env(['VIEW_CACHE_FILES' => $path])
                ->forever()
                ->command(Application::formatCommandString('view:cache --ansi'))
            );
        });

        $shards->each(fn (string $path) => File::delete($path));

        if ($results->failed()) {
            $results->collect()
                ->filter(fn ($result) => $result->failed())
                ->each(fn ($result) => $this->output->write($result->output()));

            return Command::FAILURE;
        }

        $this->components->info('Blade templates cached successfully.');

        return Command::SUCCESS;
    }

    protected function detectCpuCores(): int
    {
        if (class_exists(\Fidry\CpuCoreCounter\CpuCoreCounter::class)) {
            return (new \Fidry\CpuCoreCounter\CpuCoreCounter)->getCountWithFallback(2);
        }

        $cores = match (PHP_OS_FAMILY) {
            'Linux' => (int) @shell_exec('nproc'),
            'Darwin' => (int) @shell_exec('sysctl -n hw.ncpu'),
            default => 0,
        };

        return $cores > 0 ? $cores : 2;
    }
}
