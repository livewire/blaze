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

        try {
            File::ensureDirectoryExists(
                $blazeDirectory = $this->laravel->make('config')->get('view.compiled') . '/blaze'
            );

            $shards = $views
                ->split(min($this->processes(), $views->count()))
                ->map(function (Collection $files, $i) use ($blazeDirectory) {
                    File::put($path = $blazeDirectory . '/_views_' . $i, $files->join("\n"));

                    return $path;
                });

            $this->trap([SIGINT, SIGTERM], fn () => $this->cleanup($shards));

            $results = $this->laravel->make(ProcessFactory::class)->concurrently(function (Pool $pool) use ($shards) {
                $shards->each(fn (string $path, int $i) => $pool
                    ->as($i)
                    ->path(base_path())
                    ->env(['VIEW_CACHE_FILES' => $path])
                    ->forever()
                    ->command(Application::formatCommandString('view:cache --ansi'))
                );
            });
        } catch (\Throwable) {
            $this->cleanup($shards);

            return parent::handle();
        }

        $this->cleanup($shards);

        if ($results->failed()) {
            $results->collect()
                ->filter(fn ($result) => $result->failed())
                ->each(fn ($result) => $this->output->write($result->output()));

            return Command::FAILURE;
        }

        $this->components->info('Blade templates cached successfully.');

        return Command::SUCCESS;
    }

    protected function cleanup(Collection $shards): void
    {
        $shards->each(fn (string $path) => File::delete($path));
    }

    protected function processes(): int
    {
        if ($this->hasOption('processes')) {
            return (int) $this->option('processes');
        }

        if (class_exists(\Fidry\CpuCoreCounter\CpuCoreCounter::class)) {
            return (new \Fidry\CpuCoreCounter\CpuCoreCounter)->getCountWithFallback(2);
        }

        $cores = match (PHP_OS_FAMILY) {
            'Linux' => (int) @shell_exec('nproc')
                ?: (int) @shell_exec('getconf _NPROCESSORS_ONLN'),
            'Darwin' => (int) @shell_exec('sysctl -n hw.logicalcpu')
                ?: (int) @shell_exec('sysctl -n hw.ncpu'),
            'BSD' => (int) @shell_exec('getconf NPROCESSORS_ONLN'),
            default => 0,
        };

        if ($cores <= 0 && @is_file('/proc/cpuinfo')) {
            $cpuInfo = @file_get_contents('/proc/cpuinfo');
            if ($cpuInfo !== false) {
                $cores = substr_count($cpuInfo, 'processor');
            }
        }


        return $cores > 0 ? $cores : 2;
    }
}
