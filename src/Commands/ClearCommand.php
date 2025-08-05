<?php

namespace Livewire\Blaze\Commands;

use Illuminate\Console\Command;
use Livewire\Blaze\BlazeOptimizer;
use Livewire\Blaze\BlazeCache;
use Illuminate\Support\Facades\File;

class ClearCommand extends Command
{
    protected $signature = 'blaze:clear';
    
    protected $description = 'Clear all Blaze optimizations and caches';

    public function handle(BlazeOptimizer $optimizer, BlazeCache $cache)
    {
        $this->info('Clearing Blaze caches and optimizations...');
        
        // Clear optimizer cache
        $optimizer->clearCache();
        
        // Clear Blaze cache
        $cache->flush();
        
        // Clear compiled views
        $compilePath = config('blaze.compile.path');
        if ($compilePath && File::exists($compilePath)) {
            File::deleteDirectory($compilePath);
            File::makeDirectory($compilePath, 0755, true);
        }
        
        // Clear manifest
        $manifestPath = config('blaze.compile.manifest');
        if ($manifestPath && File::exists($manifestPath)) {
            File::delete($manifestPath);
        }
        
        $this->info('✅ Blaze caches cleared successfully!');
        
        return Command::SUCCESS;
    }
}