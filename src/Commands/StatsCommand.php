<?php

namespace Livewire\Blaze\Commands;

use Illuminate\Console\Command;
use Livewire\Blaze\BlazeOptimizer;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Cache;

class StatsCommand extends Command
{
    protected $signature = 'blaze:stats';
    
    protected $description = 'Display Blaze optimization statistics';

    public function handle(BlazeOptimizer $optimizer)
    {
        $this->info('Blaze Optimization Statistics');
        $this->line('==============================');
        
        // Configuration status
        $this->line('');
        $this->line('Configuration:');
        $this->table(
            ['Setting', 'Status'],
            [
                ['Blaze Enabled', config('blaze.enabled') ? '✅ Yes' : '❌ No'],
                ['Cache Enabled', config('blaze.cache.enabled') ? '✅ Yes' : '❌ No'],
                ['Cache Driver', config('blaze.cache.driver', 'file')],
                ['Cache TTL', config('blaze.cache.ttl', 3600) . ' seconds'],
                ['Component Inlining', config('blaze.optimization.inline_components') ? '✅ Yes' : '❌ No'],
                ['Precompilation', config('blaze.optimization.precompile_components') ? '✅ Yes' : '❌ No'],
                ['Lazy Loading', config('blaze.optimization.lazy_load_components') ? '✅ Yes' : '❌ No'],
                ['Output Minification', config('blaze.optimization.minify_output') ? '✅ Yes' : '❌ No'],
                ['Monitoring', config('blaze.monitoring.enabled') ? '✅ Yes' : '❌ No'],
            ]
        );
        
        // Cache statistics
        $this->line('');
        $this->line('Cache Statistics:');
        
        $cachePrefix = config('blaze.cache.prefix', 'blaze_');
        $cacheKeys = Cache::getStore() instanceof \Illuminate\Cache\FileStore 
            ? $this->getFileCacheKeys($cachePrefix)
            : ['Unable to retrieve cache statistics'];
        
        $this->info('Cached Components: ' . count($cacheKeys));
        
        // Compiled views statistics
        $compilePath = config('blaze.compile.path');
        if ($compilePath && File::exists($compilePath)) {
            $compiledFiles = File::files($compilePath);
            $totalSize = collect($compiledFiles)->sum(function ($file) {
                return $file->getSize();
            });
            
            $this->line('');
            $this->line('Compiled Views:');
            $this->info('Total Compiled: ' . count($compiledFiles));
            $this->info('Total Size: ' . $this->formatBytes($totalSize));
        }
        
        // Performance metrics (if monitoring is enabled)
        if (config('blaze.monitoring.enabled')) {
            $metrics = $optimizer->getMetrics();
            
            if (count($metrics) > 0) {
                $this->line('');
                $this->line('Performance Metrics:');
                
                $avgDuration = collect($metrics)->avg('duration');
                $maxDuration = collect($metrics)->max('duration');
                $minDuration = collect($metrics)->min('duration');
                
                $this->table(
                    ['Metric', 'Value'],
                    [
                        ['Average Render Time', round($avgDuration, 2) . ' ms'],
                        ['Max Render Time', round($maxDuration, 2) . ' ms'],
                        ['Min Render Time', round($minDuration, 2) . ' ms'],
                        ['Total Components Tracked', count($metrics)],
                    ]
                );
                
                // Show slowest components
                $slowest = collect($metrics)
                    ->sortByDesc('duration')
                    ->take(5);
                
                if ($slowest->count() > 0) {
                    $this->line('');
                    $this->line('Slowest Components:');
                    $this->table(
                        ['Component', 'Duration (ms)'],
                        $slowest->map(function ($metric) {
                            return [
                                $metric['view'],
                                round($metric['duration'], 2)
                            ];
                        })->toArray()
                    );
                }
            }
        }
        
        return Command::SUCCESS;
    }
    
    protected function getFileCacheKeys($prefix)
    {
        try {
            $cachePath = config('cache.stores.file.path');
            if (! File::exists($cachePath)) {
                return [];
            }
            
            $files = File::files($cachePath);
            $keys = [];
            
            foreach ($files as $file) {
                $content = File::get($file->getPathname());
                if (str_contains($content, $prefix)) {
                    $keys[] = $file->getFilename();
                }
            }
            
            return $keys;
        } catch (\Exception $e) {
            return [];
        }
    }
    
    protected function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}