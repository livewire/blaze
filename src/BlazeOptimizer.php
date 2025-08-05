<?php

namespace Livewire\Blaze;

use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Cache;

class BlazeOptimizer
{
    protected BlazeCompiler $compiler;
    protected array $config;
    protected array $metrics = [];

    public function __construct(BlazeCompiler $compiler, array $config = [])
    {
        $this->compiler = $compiler;
        $this->config = $config;
    }

    public function boot()
    {
        if ($this->config['optimization']['precompile_components'] ?? true) {
            $this->precompileComponents();
        }

        if ($this->config['monitoring']['enabled'] ?? false) {
            $this->registerMonitoring();
        }

        $this->registerViewComposer();
    }

    protected function precompileComponents()
    {
        if (! $this->config['components']['auto_discover'] ?? true) {
            return;
        }

        foreach ($this->config['components']['paths'] ?? [] as $path) {
            if (! is_dir($path)) {
                continue;
            }

            $this->precompileDirectory($path);
        }
    }

    protected function precompileDirectory($path)
    {
        $files = glob($path . '/**/*.blade.php');

        foreach ($files as $file) {
            if ($this->shouldExclude($file)) {
                continue;
            }

            try {
                $this->compiler->compile($file);
            } catch (\Exception $e) {
                if ($this->config['monitoring']['log_performance'] ?? false) {
                    logger()->error('Blaze precompilation failed', [
                        'file' => $file,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    protected function shouldExclude($file)
    {
        foreach ($this->config['components']['exclude'] ?? [] as $pattern) {
            if (fnmatch($pattern, $file)) {
                return true;
            }
        }

        return false;
    }

    protected function registerMonitoring()
    {
        View::composer('*', function ($view) {
            $start = microtime(true);

            app()->terminating(function () use ($view, $start) {
                $duration = (microtime(true) - $start) * 1000;

                $this->metrics[] = [
                    'view' => $view->getName(),
                    'duration' => $duration,
                    'timestamp' => now(),
                ];

                if ($duration > ($this->config['monitoring']['threshold_ms'] ?? 100)) {
                    if ($this->config['monitoring']['log_performance'] ?? false) {
                        logger()->warning('Slow Blade component detected', [
                            'view' => $view->getName(),
                            'duration_ms' => $duration,
                        ]);
                    }
                }
            });
        });
    }

    protected function registerViewComposer()
    {
        View::composer('*', function ($view) {
            if ($this->config['cache']['enabled'] ?? true) {
                $this->applyCaching($view);
            }
        });
    }

    protected function applyCaching($view)
    {
        $cacheKey = $this->getCacheKey($view);
        $ttl = $this->config['cache']['ttl'] ?? 3600;

        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            $view->with('__blaze_cached', $cached);
        } else {
            app()->terminating(function () use ($view, $cacheKey, $ttl) {
                Cache::put($cacheKey, $view->render(), $ttl);
            });
        }
    }

    protected function getCacheKey($view)
    {
        $prefix = $this->config['cache']['prefix'] ?? 'blaze_';
        return $prefix . md5($view->getName() . serialize($view->getData()));
    }

    public function getMetrics()
    {
        return $this->metrics;
    }

    public function clearCache()
    {
        $prefix = $this->config['cache']['prefix'] ?? 'blaze_';
        Cache::flush(); // In production, implement more targeted clearing
        $this->compiler->clearComponentCache();
    }
}