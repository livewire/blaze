<?php

namespace Livewire\Blaze\Commands;

use Illuminate\Console\Application;
use Illuminate\Console\Command;
use Illuminate\Foundation\Console\ViewCacheCommand as BaseCommand;
use Illuminate\Process\Pool;
use Illuminate\Support\Collection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Finder\SplFileInfo;
use Illuminate\Process\Factory as ProcessFactory;

#[AsCommand(name: 'view:cache')]
class ViewCacheParallelCommand extends BaseCommand
{
    protected $signature = 'view:cache {--parallel} {--processes}';

    public function handle()
    {
        if (! $this->option('parallel')) {
            return parent::handle();
        }

        if (isset($_SERVER['VIEW_CACHE_SHARD'])) {
            $files = json_decode(base64_decode($_SERVER['VIEW_CACHE_SHARD']), true);

            $compiler = $this->laravel['view']->getEngineResolver()->resolve('blade')->getCompiler();

            foreach ($files as $file) {
                $compiler->compile($file);
            }

            return Command::SUCCESS;
        }

        $this->callSilent('view:clear');

        $files = $this->paths()
            ->flatMap(fn ($path) => $this->bladeFilesIn([$path]))
            ->map(fn (SplFileInfo $file) => $file->getRealPath())
            ->values();

        if ($files->isEmpty()) {
            $this->components->info('No Blade templates found.');
            return;
        }

        $cores = (int) ($this->option('processes') ?: $this->detectCpuCores());
        $shards = $files->split(min($cores, $files->count()));

        $this->laravel[ProcessFactory::class]->concurrently(function (Pool $pool) use ($shards) {
            $shards->each(fn (Collection $files, int $i) => $pool->as($i)
                ->path(base_path())
                ->env(['VIEW_CACHE_SHARD' => base64_encode($files->toJson())])
                ->forever()
                ->command(Application::formatCommandString('view:cache --parallel'))
            );
        });

        $this->newLine();

        $this->components->info('Blade templates cached successfully.');
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
