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

        if (! $this->isFoldable($source)) {
            return $component;
        }

        if (! $this->isSafeToFold($source, $component)) {
            return $component;
        }

        $this->checkProblematicPatterns($source);

        try {
            app('blaze')->startFolding();

            $foldable = new Foldable($node, $source, $this->renderBlade);

            $folded = $foldable->fold();

            Event::dispatch(new ComponentFolded(
                name: $source->name,
                path: $source->path,
                filemtime: filemtime($source->path),
            ));

            return new TextNode($folded);
        } catch (\Exception $e) {
            if (app('blaze')->isDebugging()) {
                throw $e;
            }

            return $node;
        } finally {
            app('blaze')->stopFolding();
        } 
    }
    
    protected function isFoldable(ComponentSource $source): bool
    {
        if ($source->directives->blaze('fold') === false) {
            return false;
        }

        if ($source->directives->blaze('fold') === true) {
            return true;
        }

        return $this->config->shouldFold($source->path);
    }

    protected function isSafeToFold(ComponentSource $source, ComponentNode $node)
    {
        // We can't fold when entire attributes are passed through...
        if ($node->attributes['attributes']?->dynamic) {
            return false;
        }

        // Build a list of unsafe props: defined + unsafe - safe...
        $unsafe = array_merge(array_keys($source->directives->array('props')), $source->directives->blaze('unsafe'));
        $unsafe = array_diff($unsafe, array_keys($source->directives->array('safe')));

        foreach ($node->attributes as $attribute) {
            if ($attribute->dynamic && in_array($attribute->name, $unsafe)) {
                return false;
            }
        }

        foreach ($node->slots as $slot) {
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
