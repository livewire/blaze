<?php

namespace Livewire\Blaze;

use Livewire\Blaze\Events\ComponentFolded;

class FrontMatter
{
    public function compileFromEvents(array $events): string
    {
        $frontmatter = '';

        foreach ($events as $event) {
            if (! ($event instanceof ComponentFolded)) {
                throw new \Exception('Event is not a ComponentFolded event');
            }

            $frontmatter .= "<?php # [BlazeFolded]:{". $event->name ."}:{". $event->path ."}:{".$event->filemtime."} ?>\n";
        }

        return $frontmatter;
    }

    public function parseFromTemplate(string $template): array
    {
        preg_match_all('/<'.'?php # \[BlazeFolded\]:\{([^}]+)\}:\{([^}]+)\}:\{([^}]+)\} \?>/', $template, $matches, PREG_SET_ORDER);

        return $matches;
    }

    public function sourceContainsExpiredFoldedDependencies(string $source): bool
    {
        // Parse frontmatter to get folded component metadata
        $foldedComponents = $this->parseFromTemplate($source);
        
        if (empty($foldedComponents)) {
            return false;
        }
        
        // Check each folded component for expiration
        foreach ($foldedComponents as $match) {
            $componentName = $match[1];
            $componentPath = $match[2];
            $storedFilemtime = (int) $match[3];
            
            // Debug output (temporary)
            // error_log("Checking: $componentName at $componentPath, stored: $storedFilemtime");
            
            // Check if component file still exists
            if (!file_exists($componentPath)) {
                // File no longer exists, needs recompilation
                // error_log("File does not exist: $componentPath");
                return true;
            }
            
            // Check if file has been modified since it was folded
            $currentFilemtime = filemtime($componentPath);
            // error_log("Current filemtime: $currentFilemtime, stored: $storedFilemtime");
            if ($currentFilemtime > $storedFilemtime) {
                // Component has been modified, needs recompilation
                // error_log("File is expired: $componentPath");
                return true;
            }
        }
        
        // No expired dependencies found
        return false;
    }
}
