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
use Livewire\Blaze\BladeRenderer;
use Livewire\Blaze\BladeService;
use Livewire\Blaze\BlazeManager;
use Illuminate\Support\Arr;
use Livewire\Blaze\Config;
use Livewire\Blaze\Parser\Nodes\DirectiveNode;
use Livewire\Blaze\Parser\Walker;
use Livewire\Blaze\Support\DirectiveStack;
use Throwable;
use Livewire\Blaze\Support\ComponentRepository;

/**
 * Determines whether a component should be folded and orchestrates the folding process.
 */
class Folder
{
    public function __construct(
        protected Config $config,
        protected BladeService $blade,
        protected BladeRenderer $renderer,
        protected BlazeManager $manager,
        protected ComponentRepository $components,
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

        $component = $this->components->get($node->name);

        if (! $component) {
            return $node;
        }

        if (! $this->shouldFold($component)) {
            return $node;
        }

        if (! $this->isSafeToFold($component, $node)) {
            return $node;
        }

        $this->checkProblematicPatterns($component);

        try {
            $foldable = new Foldable($node, $component, $this->renderer, $this->blade);

            $html = $foldable->fold();

            Event::dispatch(new ComponentFolded(
                name: $node->name,
                path: $component->path,
                filemtime: filemtime($component->path),
            ));

            return new TextNode('<?php ob_start(); ?>' . $html . '<?php echo ltrim(ob_get_clean()); ?>');
        } catch (Throwable $th) {
            if ($this->manager->shouldThrow()) {
                throw $th;
            }

            return $node;
        }
    }
    
    /**
     * Check if the component should be folded based on directive and config settings.
     */
    protected function shouldFold(ComponentSource $source): bool
    {
        $shouldFold = $source->template->directives->blaze('fold');

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
        if ($this->slotsAreWrappedInDirective($node)) {
            return false;
        }

        $dynamicAttributes = array_filter($node->attributes, fn ($attribute) => ! $attribute->isStaticValue());

        foreach ($source->template->directives->aware() as $prop) {
            if (! isset($node->attributes[$prop])
                && isset($node->parentsAttributes[$prop])
                && ! $node->parentsAttributes[$prop]->isStaticValue()
            ) {
                $dynamicAttributes[$prop] = $node->parentsAttributes[$prop];
            }
        }

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

        $props = $source->template->directives->props();
        $aware = $source->template->directives->aware();

        $safe = Arr::wrap($source->template->directives->blaze('safe'));
        $unsafe = Arr::wrap($source->template->directives->blaze('unsafe'));

        if (in_array('*', $safe)) {
            return true;
        }

        if (in_array('*', $unsafe) && (count($dynamicAttributes) > 0 || count($node->children) > 0)) {
            return false;
        }

        if (in_array('slot', $unsafe)) {
            // Check for explicit default slot...
            if (array_filter($node->children, fn ($child) => $child instanceof SlotNode && $child->name === 'slot')) {
                return false;
            }

            $looseContent = array_filter($node->children, fn ($child) => ! $child instanceof SlotNode);
            $looseContent = join('', array_map(fn ($child) => $child->render(), $looseContent));
            
            if (trim($looseContent) !== '') {
                return false;
            }
        }

        if (in_array('attributes', $unsafe)) {
            $unsafe = array_merge($unsafe, array_diff(array_keys($node->attributes), $props));
        }

        $unsafe = array_diff(array_merge($props, $aware, $unsafe), $safe);

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
     * Check if a slot is wrapped in a directive.
     */
    protected function slotsAreWrappedInDirective(ComponentNode $node): bool
    {
        $stack = DirectiveStack::make($this->blade->customConditions());

        foreach ($node->children as $child) {
            if ($child instanceof DirectiveNode) {
                $stack->add($child->name);
            }

            if ($child instanceof SlotNode && $stack->open()) {
                return true;
            }
        }

        return false;
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
        $insideUnblaze = false;

        foreach (Walker::iterate($source->template->nodes) as $node) {
            if ($node->isDirective('unblaze')) {
                $insideUnblaze = true;

                continue;
            }

            if ($node->isDirective('endunblaze')) {
                $insideUnblaze = false;

                continue;
            }

            if ($insideUnblaze) {
                continue;
            }

            if ($node->isDirective('once')) {
                throw InvalidBlazeFoldUsageException::forOnce($source->path);
            }

            if ($node->containsPhp('$errors')) {
                throw InvalidBlazeFoldUsageException::forErrors($source->path);
            }

            if ($node->containsPhp('session(')) {
                throw InvalidBlazeFoldUsageException::forSession($source->path);
            }

            if ($node->isDirective('error')) {
                throw InvalidBlazeFoldUsageException::forError($source->path);
            }

            if ($node->isDirective('csrf')) {
                throw InvalidBlazeFoldUsageException::forCsrf($source->path);
            }

            if ($node->containsPhp('auth()')) {
                throw InvalidBlazeFoldUsageException::forAuth($source->path);
            }

            if ($node->containsPhp('request()')) {
                throw InvalidBlazeFoldUsageException::forRequest($source->path);
            }

            if ($node->containsPhp('old(')) {
                throw InvalidBlazeFoldUsageException::forOld($source->path);
            }
        }
    }
}
