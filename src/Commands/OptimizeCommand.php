<?php

namespace Livewire\Blaze\Commands;

use Illuminate\Console\Command;
use Livewire\Blaze\BlazeOptimizer;

class OptimizeCommand extends Command
{
    protected $signature = 'blaze:optimize {--force : Force re-optimization of all components}';
    
    protected $description = 'Optimize Blade components for better performance';

    public function handle(BlazeOptimizer $optimizer)
    {
        $this->info('Optimizing Blade components...');
        
        $start = microtime(true);
        
        if ($this->option('force')) {
            $this->info('Clearing existing optimizations...');
            $optimizer->clearCache();
        }
        
        // Re-run the boot process to precompile components
        $optimizer->boot();
        
        $duration = round(microtime(true) - $start, 2);
        
        $this->info("✅ Blade components optimized successfully in {$duration} seconds!");
        
        if (config('blaze.monitoring.enabled')) {
            $metrics = $optimizer->getMetrics();
            if (count($metrics) > 0) {
                $this->table(
                    ['Component', 'Duration (ms)'],
                    collect($metrics)->map(function ($metric) {
                        return [
                            $metric['view'],
                            round($metric['duration'], 2)
                        ];
                    })->toArray()
                );
            }
        }
        
        return Command::SUCCESS;
    }
}