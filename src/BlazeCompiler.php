<?php

namespace Livewire\Blaze;

use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\Filesystem\Filesystem;

class BlazeCompiler extends BladeCompiler
{
    protected array $config;
    protected array $componentCache = [];
    protected array $optimizationPasses = [];

    public function __construct(Filesystem $files, $cachePath, array $config = [])
    {
        parent::__construct($files, $cachePath);
        $this->config = $config;
        $this->registerOptimizationPasses();
    }

    protected function registerOptimizationPasses()
    {
        if ($this->config['optimization']['inline_components'] ?? true) {
            $this->optimizationPasses[] = new Optimizations\InlineComponents();
        }

        if ($this->config['optimization']['slot_optimization'] ?? true) {
            $this->optimizationPasses[] = new Optimizations\SlotOptimization();
        }

        if ($this->config['optimization']['minify_output'] ?? false) {
            $this->optimizationPasses[] = new Optimizations\MinifyOutput();
        }
    }

    public function compile($path = null)
    {
        if (! is_null($path)) {
            $this->setPath($path);
        }

        if (! $this->cachePath) {
            throw new \InvalidArgumentException('Please provide a valid cache path.');
        }

        $contents = $this->compileString($this->files->get($this->getPath()));

        $contents = $this->applyOptimizations($contents);

        if (! empty($this->getPath())) {
            $contents = $this->appendFilePath($contents);
        }

        $this->files->put(
            $this->getCompiledPath($this->getPath()),
            $contents
        );
    }

    protected function applyOptimizations($contents)
    {
        foreach ($this->optimizationPasses as $pass) {
            $contents = $pass->apply($contents, $this->config);
        }

        return $contents;
    }

    public function compileComponent($component)
    {
        $cacheKey = $this->getComponentCacheKey($component);

        if ($this->config['optimization']['component_caching'] ?? true) {
            if (isset($this->componentCache[$cacheKey])) {
                return $this->componentCache[$cacheKey];
            }
        }

        $compiled = $this->compileComponentBase($component);
        $compiled = $this->applyComponentOptimizations($compiled);

        if ($this->config['optimization']['component_caching'] ?? true) {
            $this->componentCache[$cacheKey] = $compiled;
        }

        return $compiled;
    }

    protected function compileComponentBase($component)
    {
        return parent::compileString($component);
    }

    protected function applyComponentOptimizations($compiled)
    {
        if ($this->config['optimization']['lazy_load_components'] ?? true) {
            $compiled = $this->wrapInLazyLoad($compiled);
        }

        return $compiled;
    }

    protected function wrapInLazyLoad($compiled)
    {
        return "<?php if (\\Livewire\\Blaze\\BlazeCache::shouldRender()): ?>{$compiled}<?php endif; ?>";
    }

    protected function getComponentCacheKey($component)
    {
        return md5($component);
    }

    public function clearComponentCache()
    {
        $this->componentCache = [];
    }
}