<?php

namespace Livewire\Blaze;

use Livewire\Blaze\Events\ComponentFolded;

/**
 * Manages BlazeFolded front matter comments that track folded component dependencies.
 */
class FrontMatter
{
    /**
     * Generate front matter PHP comments from ComponentFolded events.
     */
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

    /**
     * Check if any folded component referenced in the source has been modified.
     */
    public function sourceContainsExpiredFoldedDependencies(string $source): bool
    {
        $foldedComponents = $this->parseFromTemplate($source);

        if (empty($foldedComponents)) {
            return false;
        }

        foreach ($foldedComponents as $match) {
            $componentPath = $match[2];

            $storedFilemtime = (int) $match[3];

            if (! file_exists($componentPath)) {
                return true;
            }

            $currentFilemtime = filemtime($componentPath);

            if ($currentFilemtime > $storedFilemtime) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract BlazeFolded markers from a compiled template.
     */
    public function parseFromTemplate(string $template): array
    {
        preg_match_all('/<'.'?php # \[BlazeFolded\]:\{([^}]+)\}:\{([^}]+)\}:\{([^}]+)\} \?>/', $template, $matches, PREG_SET_ORDER);

        return $matches;
    }
}
