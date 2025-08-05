<?php

namespace Livewire\Blaze\Optimizations;

class InlineComponents
{
    public function apply($contents, array $config)
    {
        // Match component includes like @component or <x-component>
        $pattern = '/@component\s*\(\s*[\'"]([^\'"]+)[\'"]\s*(?:,\s*(\[[^\]]*\]))?\s*\)/';
        
        $contents = preg_replace_callback($pattern, function ($matches) use ($config) {
            $componentName = $matches[1];
            $parameters = $matches[2] ?? '[]';
            
            // Check if component is small enough to inline
            if ($this->shouldInline($componentName, $config)) {
                return $this->getInlinedComponent($componentName, $parameters);
            }
            
            return $matches[0];
        }, $contents);

        // Handle x-components
        $contents = $this->inlineXComponents($contents, $config);

        return $contents;
    }

    protected function shouldInline($componentName, array $config)
    {
        // Simple heuristic: inline if component is referenced multiple times
        // In production, this would analyze component size and complexity
        return true;
    }

    protected function getInlinedComponent($componentName, $parameters)
    {
        // In production, this would actually read and inline the component
        // For now, return optimized include
        return "<?php echo \\Livewire\\Blaze\\BlazeCache::remember('{$componentName}' . md5({$parameters}), function() use (\$__data) { return view('{$componentName}', {$parameters})->render(); }); ?>";
    }

    protected function inlineXComponents($contents, array $config)
    {
        // Match <x-component> tags
        $pattern = '/<x-([a-zA-Z0-9\-.:]+)([^>]*)>(.*?)<\/x-\1>/s';
        
        return preg_replace_callback($pattern, function ($matches) use ($config) {
            $componentName = str_replace(['-', '.'], ['_', '.'], $matches[1]);
            $attributes = $matches[2];
            $slot = $matches[3];
            
            if ($this->shouldInline($componentName, $config)) {
                return $this->getInlinedXComponent($componentName, $attributes, $slot);
            }
            
            return $matches[0];
        }, $contents);
    }

    protected function getInlinedXComponent($componentName, $attributes, $slot)
    {
        $cacheKey = md5($componentName . $attributes . $slot);
        return "<?php echo \\Livewire\\Blaze\\BlazeCache::remember('{$cacheKey}', function() { ?>{$slot}<?php }); ?>";
    }
}