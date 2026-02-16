<?php

namespace Livewire\Blaze\Folder;

use Illuminate\Support\Facades\Event;
use Livewire\Blaze\Events\ComponentFolded;
use Livewire\Blaze\Exceptions\InvalidBlazeFoldUsageException;
use Livewire\Blaze\Parser\Nodes\ComponentNode;
use Livewire\Blaze\Parser\Nodes\Node;
use Livewire\Blaze\Parser\Nodes\SlotNode;
use Livewire\Blaze\Parser\Nodes\TextNode;
use Livewire\Blaze\Support\ComponentSource;
use Illuminate\Support\Arr;
use Livewire\Blaze\Blaze;
use Livewire\Blaze\Config;

/**
 * Determines whether a component should be folded and orchestrates the folding process.
 */
class Folder
{
    public function __construct(
        protected ?Config $config = null,
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
        $shouldFold = $source->directives->blaze('fold');

        if ($this->config && is_null($shouldFold)) {
            return $this->config->shouldFold($source->path);
        }

        return $shouldFold;
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

        foreach ($node->children as $child) {
            if ($child instanceof SlotNode) {
                if ($this->slotHasDynamicAttributes($child)) {
                    return false;
                }
            }
        }

        $props = $source->directives->props();
        $safe = Arr::wrap($source->directives->blaze('safe'));
        $unsafe = Arr::wrap($source->directives->blaze('unsafe'));

        if (in_array('*', $safe)) {
            return true;
        }

        if (in_array('*', $unsafe) && (count($dynamicAttributes) > 0 || count($node->children) > 0)) {
            return false;
        }

        if (in_array('slot', $unsafe) && array_filter($node->children, fn ($child) => ! $child instanceof SlotNode)) { // TODO: we should also check name="slot"
            return false;
        }

        if (in_array('attributes', $unsafe)) {
            $unsafe = array_merge($unsafe, array_diff(array_keys($node->attributes), $props));
        }

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
