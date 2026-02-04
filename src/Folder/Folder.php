<?php

namespace Livewire\Blaze\Folder;

use Illuminate\Support\Facades\Event;
use Livewire\Blaze\Events\ComponentFolded;
use Livewire\Blaze\Exceptions\InvalidBlazeFoldUsageException;
use Livewire\Blaze\Nodes\ComponentNode;
use Livewire\Blaze\Nodes\Node;
use Livewire\Blaze\Nodes\TextNode;
use Livewire\Blaze\Support\ComponentSource;
use Closure;
use Illuminate\Support\Arr;
use Livewire\Blaze\Blaze;
use Livewire\Blaze\BlazeConfig;

class Folder
{
    public function __construct(
        protected Closure $renderBlade,
        protected Closure $renderNodes,
        protected Closure $componentNameToPath,
        protected BlazeConfig $config,
    ) {
    }

    public function fold(Node $node): Node
    {
        if (! $node instanceof ComponentNode) {
            return $node;
        }

        $component = $node;

        $source = new ComponentSource($component->name);

        if (! $source->exists()) {
            return $component;
        }

        if (! $this->shouldFold($source)) {
            return $component;
        }

        if (! $this->isSafeToFold($source, $component)) {
            return $component;
        }

        $this->checkProblematicPatterns($source);

        try {
            Blaze::startFolding();

            $foldable = new Foldable($node, $source, $this->renderBlade);

            $html = $foldable->fold();

            Event::dispatch(new ComponentFolded(
                name: $source->name,
                path: $source->path,
                filemtime: filemtime($source->path),
            ));

            return new TextNode($html);
        } catch (\Exception $e) {
            if (Blaze::isDebugging()) {
                throw $e;
            }

            return $node;
        } finally {
            Blaze::stopFolding();
        } 
    }
    
    protected function shouldFold(ComponentSource $source): bool
    {
        if ($source->directives->blaze('fold') === false) {
            return false;
        }

        if ($source->directives->blaze('fold') === true) {
            return true;
        }

        return $this->config->shouldFold($source->path);
    }

    protected function isSafeToFold(ComponentSource $source, ComponentNode $node): bool
    {
        // We can't fold with :attributes / :$attributes spread...
        if (($node->attributes['attributes'] ?? null)?->dynamic) {
            return false;
        }

        $safe = Arr::wrap($source->directives->blaze('safe'));

        if (in_array('*', $safe)) {
            return true;
        }

        // Collect prop names and safe/unsafe parameters..
        $props = array_keys($source->directives->array('props') ?? []);
        $unsafe = Arr::wrap($source->directives->blaze('unsafe'));

        // Build a final list of unsafe props = defined + unsafe - safe
        $unsafe = array_merge($props, $unsafe);
        $unsafe = array_diff($unsafe, $safe);

        // Check if any dynamic attributes are unsafe...
        foreach ($node->attributes as $attribute) {
            if ($attribute->dynamic && in_array($attribute->propName, $unsafe)) {
                return false;
            }
        }

        $slots = $node->slots();

        foreach ($slots as $slot) {
            if (in_array($slot->name, $unsafe)) {
                return false;
            }
        }

        return true;
    }

    protected function checkProblematicPatterns(ComponentSource $source): void
    {
        // Strip out @unblaze blocks before validation since they can contain dynamic content
        $sourceWithoutUnblaze = preg_replace('/@unblaze.*?@endunblaze/s', '', $source->content);

        $problematicPatterns = [
            '@once' => 'forOnce',
            '\\$errors' => 'forErrors',
            'session\\(' => 'forSession',
            '@error\\(' => 'forError',
            '@csrf' => 'forCsrf',
            'auth\\(\\)' => 'forAuth',
            'request\\(\\)' => 'forRequest',
            'old\\(' => 'forOld',
        ];

        foreach ($problematicPatterns as $pattern => $factoryMethod) {
            if (preg_match('/'.$pattern.'/', $sourceWithoutUnblaze)) {
                throw InvalidBlazeFoldUsageException::{$factoryMethod}($source->path);
            }
        }
    }
}
