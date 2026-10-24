<?php

namespace Livewire\Blaze;

use Livewire\Blaze\Events\ComponentFolded;
use Livewire\Blaze\Support\Utils;

/**
 * Manages front matter comments that track the Blaze version and folded component dependencies.
 */
class FrontMatter
{
    /**
     * Generate the front matter PHP comment that marks output compiled by the current Blaze version.
     */
    public function compileVersion(): string
    {
        return $this->versionMarker()."\n";
    }

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
     * Check if a compiled file was compiled by the current Blaze version.
     */
    public function isCurrentVersion(string $path): bool
    {
        $source = @file_get_contents($path);

        return $source !== false && str_contains($source, $this->versionMarker());
    }

    /**
     * Check if a compiled file was compiled by another Blaze version or references modified folded components.
     */
    public function isExpired(string $path): bool
    {
        $source = @file_get_contents($path);

        if ($source === false) {
            return false;
        }

        return ! str_contains($source, $this->versionMarker())
            || $this->containsExpiredFoldedDependencies($source);
    }

    /**
     * Check if any folded component referenced in the compiled source has been modified.
     */
    public function containsExpiredFoldedDependencies(string $source): bool
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

    /**
     * Get the PHP comment that identifies the current Blaze version.
     */
    protected function versionMarker(): string
    {
        return '<'.'?php # [BlazeVersion]:{'.Utils::COMPILED_VERSION.'} ?>';
    }
}
