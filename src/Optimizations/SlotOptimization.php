<?php

namespace Livewire\Blaze\Optimizations;

class SlotOptimization
{
    public function apply($contents, array $config)
    {
        // Optimize slot rendering
        $contents = $this->optimizeNamedSlots($contents);
        $contents = $this->optimizeDefaultSlots($contents);
        $contents = $this->removeEmptySlots($contents);
        
        return $contents;
    }

    protected function optimizeNamedSlots($contents)
    {
        // Match @slot directives
        $pattern = '/@slot\s*\(\s*[\'"]([^\'"]+)[\'"]\s*(?:,\s*(\[[^\]]*\]))?\s*\)(.*?)@endslot/s';
        
        return preg_replace_callback($pattern, function ($matches) {
            $slotName = $matches[1];
            $attributes = $matches[2] ?? '[]';
            $slotContent = trim($matches[3]);
            
            // If slot content is small, inline it
            if (strlen($slotContent) < 500) {
                return "<?php \$__env->slot('{$slotName}', {$attributes}); ?>{$slotContent}<?php \$__env->endSlot(); ?>";
            }
            
            // For larger slots, use caching
            $cacheKey = md5($slotName . $slotContent);
            return "<?php \$__env->slot('{$slotName}', {$attributes}); echo \\Livewire\\Blaze\\BlazeCache::remember('{$cacheKey}', function() { ?>{$slotContent}<?php }); \$__env->endSlot(); ?>";
        }, $contents);
    }

    protected function optimizeDefaultSlots($contents)
    {
        // Optimize {{ $slot }} references
        $pattern = '/\{\{\s*\$slot\s*\}\}/';
        
        return preg_replace_callback($pattern, function ($matches) {
            return '<?php echo $slot ?? \'\'; ?>';
        }, $contents);
    }

    protected function removeEmptySlots($contents)
    {
        // Remove empty slot definitions
        $pattern = '/@slot\s*\(\s*[\'"][^\'"]+[\'"]\s*\)\s*@endslot/';
        
        return preg_replace($pattern, '', $contents);
    }
}