<?php

namespace Livewire\Blaze\Folder;

use Illuminate\Support\Facades\Event;
use Livewire\Blaze\Events\ComponentFolded;
use Livewire\Blaze\Exceptions\InvalidBlazeFoldUsageException;
use Livewire\Blaze\Nodes\ComponentNode;
use Livewire\Blaze\Nodes\Node;
use Livewire\Blaze\Nodes\SlotNode;
use Livewire\Blaze\Nodes\TextNode;
use Livewire\Blaze\Support\ComponentSource;
use Closure;
use Illuminate\Support\Arr;
use Livewire\Blaze\Blaze;
use Livewire\Blaze\Config;

/**
 * Determines whether a component should be folded and orchestrates the folding process.
 */
class Folder
{
    public function __construct(
        protected Config $config,
    ) {
    }

    /**
     * Attempt to fold a component node into static HTML with dynamic placeholders.
     */
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

            $foldable = new Foldable($node, $source);

            $html = $foldable->fold();

            Event::dispatch(new ComponentFolded(
                name: $source->name,
                path: $source->path,
                filemtime: filemtime($source->path),
            ));

            return new TextNode($html);
        } catch (\Exception $e) {
            if (Blaze::shouldThrow()) {
                throw $e;
            }

            return $node;
        } finally {
            Blaze::stopFolding();
        } 
    }
    
    /**
     * Check if the component should be folded based on directive and config settings.
     */
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

    /**
     * Determine if a component is safe to fold based on its safe/unsafe attribute declarations.
     */
    protected function isSafeToFold(ComponentSource $source, ComponentNode $node): bool
    {
        $dynamicAttributes = array_filter($node->attributes, fn ($attribute) => ! $attribute->isStaticValue());

        if (array_key_exists('attributes', $dynamicAttributes)) {
            return false;
        }

        $safe = Arr::wrap($source->directives->blaze('safe'));
        $unsafe = Arr::wrap($source->directives->blaze('unsafe'));

        if (in_array('*', $safe)) {
            return true;
        }

        if (in_array('*', $unsafe)) {
            return false;
        }

        if (in_array('slot', $unsafe) && array_filter($node->children, fn ($child) => ! $child instanceof SlotNode)) {
            return false;
        }

        // Props are unsafe by default
        $props = $source->directives->props();
        $unsafe = array_merge($unsafe, $props);

        // Expand 'attributes' keyword into actual non-prop attribute names
        if (in_array('attributes', $safe)) {
            $nonPropAttributes = array_diff(array_keys($node->attributes), $props);
            $safe = array_merge(array_diff($safe, ['attributes']), $nonPropAttributes);
        }

        if (in_array('attributes', $unsafe)) {
            $nonPropAttributes = array_diff(array_keys($node->attributes), $props);
            $unsafe = array_merge(array_diff($unsafe, ['attributes']), $nonPropAttributes);
        }

        // Final unsafe list = props + unsafe - safe
        $unsafe = array_diff(array_merge($props, $unsafe), $safe);

        foreach ($dynamicAttributes as $attribute) {
            if (in_array($attribute->propName, $unsafe)) {
                return false;
            }
        }

        foreach ($node->children as $child) {
            if ($child instanceof SlotNode) {
                if (in_array($child->name, $unsafe)) {
                    return false;
                }

                if ($this->slotHasDynamicAttributes($child)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Check if a slot has any dynamically-bound attributes.
     */
    protected function slotHasDynamicAttributes(SlotNode $slot): bool
    {
        foreach ($slot->attributes as $attribute) {
            if (! $attribute->isStaticValue()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Throw if the component source contains patterns incompatible with folding.
     */
    protected function checkProblematicPatterns(ComponentSource $source): void
    {
        // @unblaze blocks can contain dynamic content and are excluded from validation
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
