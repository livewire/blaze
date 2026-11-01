<?php

namespace Livewire\Blaze\Folder;

use Illuminate\Support\Str;
use Livewire\Blaze\BladeRenderer;
use Livewire\Blaze\BladeService;
use Livewire\Blaze\Exceptions\LeftoverPlaceholdersException;
use Livewire\Blaze\Parser\Attribute;
use Livewire\Blaze\Parser\Nodes\ComponentNode;
use Livewire\Blaze\Parser\Nodes\SlotNode;
use Livewire\Blaze\Parser\Nodes\TextNode;
use Livewire\Blaze\Support\ComponentSource;

/**
 * Performs compile-time folding of a component by rendering it with placeholder substitution.
 *
 * Dynamic attributes and slot contents are replaced with placeholders before rendering,
 * then restored afterward so they evaluate at runtime.
 */
class Foldable
{
    protected array $attributeByIndex = [];
    protected array $slotByPlaceholder = [];
    protected int $placeholderIndex = 0;

    protected ComponentNode $renderable;
    protected string $html;

    public function __construct(
        protected ComponentNode $node,
        protected string $path,
        protected BladeRenderer $renderer,
        protected BladeService $blade,
    ) {
    }

    /**
     * Fold the component: render with placeholders, then restore dynamic values.
     */
    public function fold(): string
    {
        $this->renderable = new ComponentNode(
            name: $this->node->name,
            prefix: $this->node->prefix,
            selfClosing: $this->node->selfClosing,
        );

        $this->setupAttributes();
        $this->setupSlots();

        $this->html = $this->renderer->render($this->renderable, $this->path, $this->exportScope(...));

        $this->processUncompiledAttributes();
        $this->restorePlaceholders();
        $this->ensureNoLeftoverPlaceholders();
        $this->wrapWithAwareMacros();

        return $this->html;
    }

    /**
     * Prepare component and inherited attributes for isolated rendering.
     */
    protected function setupAttributes(): void
    {
        foreach ($this->node->attributes as $key => $attribute) {
            $this->renderable->attributes[$key] = $this->prepareAttribute($attribute);
        }

        foreach ($this->node->parentsAttributes as $key => $attribute) {
            $this->renderable->parentsAttributes[$key] = $this->prepareAttribute($attribute);
        }
    }

    /**
     * Replace a dynamic attribute with a static placeholder for isolated rendering.
     */
    protected function prepareAttribute(Attribute $attribute): Attribute
    {
        if ($attribute->isStaticValue()) {
            return clone $attribute;
        }

        $index = $this->placeholderIndex++;
        $placeholder = Placeholder::make($index, $attribute->bound());

        $this->attributeByIndex[$index] = $attribute;

        return new Attribute(
            name: $attribute->name,
            value: $placeholder,
            propName: $attribute->propName,
            dynamic: false,
        );
    }

    /**
     * Replace slot children with placeholders for rendering, storing originals for restoration.
     */
    protected function setupSlots(): void
    {
        $slots = [];
        $looseContent = [];

        foreach ($this->node->children as $child) {
            if ($child instanceof SlotNode) {
                $children = [];

                if ($this->hasActualContent($child->children)) {
                    $placeholder = Placeholder::make($this->placeholderIndex++);
    
                    $this->slotByPlaceholder[$placeholder] = $child;

                    $children = [new TextNode($placeholder)];
                }

                $slots[$child->name] = new SlotNode(
                    name: $child->name,
                    attributeString: $child->attributeString,
                    slotStyle: $child->slotStyle,
                    children: $children,
                    prefix: $child->prefix,
                    closeHasName: $child->closeHasName,
                    attributes: $child->attributes,
                );
            } else {
                $looseContent[] = $child;
            }
        }

        // Synthesize a default slot from loose content when there's not an explicit one
        if ($this->hasActualContent($looseContent) && ! isset($slots['slot'])) {
            $placeholder = Placeholder::make($this->placeholderIndex++);

            $defaultSlot = new SlotNode(
                name: 'slot',
                attributeString: '',
                slotStyle: 'standard',
                children: $looseContent,
                prefix: 'x-slot',
            );

            $this->slotByPlaceholder[$placeholder] = $defaultSlot;

            $slots['slot'] = new SlotNode(
                name: 'slot',
                attributeString: '',
                slotStyle: 'standard',
                children: [new TextNode($placeholder)],
                prefix: 'x-slot',
            );
        }

        $this->renderable->children = $slots;
    }

    /**
    * Determine whether a list of nodes contains anything besides whitespace-only text.
    */
    protected function hasActualContent(array $nodes): bool
    {
        foreach ($nodes as $node) {
            if (! $node instanceof TextNode || trim($node->content) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Convert [BLAZE_ATTR:...] markers into conditional PHP for dynamic attributes.
     */
    protected function processUncompiledAttributes(): void
    {
        $this->html = preg_replace_callback('/\[BLAZE_ATTR:(' . Placeholder::PATTERN . '):(.+?)\](\r?\n)?/', function ($matches) {
            $attribute = $this->attributeFor($matches[1]);
            $name = $matches[4];

            if (! $attribute) {
                return $matches[0];
            }

            if ($attribute->bound()) {
                // x-data and wire:* get empty string for true, others get key name
                $booleanValue = ($name === 'x-data' || str_starts_with($name, 'wire:')) ? "''" : "'".addslashes($name)."'";

                return '<'.'?php if (($__blazeAttr = '.$attribute->value.') !== false && !is_null($__blazeAttr)): ?'.'>'
                . $name.'="<'.'?php echo e($__blazeAttr === true ? '.$booleanValue.' : $__blazeAttr); ?'.'>"'
                .'<'.'?php endif; unset($__blazeAttr); ?'.'>' . (isset($matches[5]) ? $matches[5] . $matches[5] : '');
            } else {
                return $name.'="'.$attribute->value.'"';
            }
        }, $this->html);
    }

    /**
     * Replace placeholders in HTML with their original dynamic values or slot content.
     *
     * Bound attributes are echoed escaped, however many times the component escaped them. PHP blocks
     * are skipped, the only ones holding placeholders are @unblaze scopes, exported by exportScope().
     */
    protected function restorePlaceholders(): void
    {
        $this->html = preg_replace_callback('/<\?php.*?\?>(*SKIP)(*FAIL)|' . Placeholder::PATTERN . '/s', function ($matches) {
            $attribute = $this->attributeFor($matches[0]);

            return match (true) {
                ! $attribute => $matches[0],
                $attribute->bound() => '{{ ' . $attribute->value . ' }}',
                default => $attribute->value,
            };
        }, $this->html);

        foreach ($this->slotByPlaceholder as $placeholder => $slot) {
            // In Blade slots are rendered using output buffer and echo syntax,
            // we need to replicate both here to handle whitespace correctly.
            $this->html = preg_replace_callback('/' . $placeholder . '(\r?\n)?/', function ($match) use ($slot) {
                $whitespace = $match[1] ?? '';

                return '<?php ob_start(); ?>' . $slot->content() . '<?php echo trim(ob_get_clean()); ?>' . $whitespace . $whitespace;
            }, $this->html);
        }
    }

    /**
     * Export the scope of an @unblaze block as PHP code that evaluates to what it holds without folding.
     *
     * A bound attribute is restored raw, like props get it, or escaped once, like the attribute bag
     * gets it. Any other value holding a placeholder, like one escaped twice or concatenated, fails
     * the fold, so the component is compiled without folding.
     */
    public function exportScope(mixed $value): string
    {
        if (is_array($value)) {
            $items = [];

            foreach ($value as $key => $item) {
                $items[] = var_export($key, true) . ' => ' . $this->exportScope($item);
            }

            return '[' . implode(', ', $items) . ']';
        }

        if (is_string($value) && $attribute = $this->attributeFor($value)) {
            return match (Placeholder::parse($value)[1]) {
                null => $this->blade->compileAttributeEchos($attribute->value),
                0 => $attribute->value,
                1 => '\Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(' . $attribute->value . ')',
                default => throw new LeftoverPlaceholdersException($this->node->name, $value),
            };
        }

        $code = var_export($value, true);

        if (stripos($code, 'BLAZE_PLACEHOLDER_') !== false) {
            throw new LeftoverPlaceholdersException($this->node->name, $code);
        }

        return $code;
    }

    /**
     * Get the attribute a placeholder stands for, or null when it's a slot or doesn't match the attribute.
     */
    protected function attributeFor(string $placeholder): ?Attribute
    {
        [$index, $escapes] = Placeholder::parse($placeholder) ?? [-1, null];

        $attribute = $this->attributeByIndex[$index] ?? null;

        if (! $attribute || $attribute->bound() !== ($escapes !== null)) {
            return null;
        }

        return $attribute;
    }

    /**
     * Fail the fold when placeholders or attribute markers are left, so the component is compiled without folding.
     */
    protected function ensureNoLeftoverPlaceholders(): void
    {
        if (preg_match_all('/BLAZE_PLACEHOLDER_[0-9]+_\S*|\[BLAZE_ATTR:[^\]]*\]/i', $this->html, $matches)) {
            throw new LeftoverPlaceholdersException(
                $this->node->name,
                implode(', ', array_unique($matches[0])),
                Str::limit($this->html, 500),
            );
        }
    }

    /**
     * Wrap output with pushConsumableComponentData calls if descendants use @aware.
     */
    protected function wrapWithAwareMacros(): void
    {
        if (! $this->renderable->attributes) {
            return;
        }

        if (! $this->node->hasAwareDescendants) {
            return;
        }

        $data = [];

        foreach ($this->node->attributes as $attribute) {
            $data[] = $this->blade->compileAttribute($attribute);
        }

        $dataString = implode(', ', $data);

        $this->html = Str::wrap($this->html,
            '<?php $__blaze->pushData(['.$dataString.']); $__env->pushConsumableComponentData(['.$dataString.']); ?>',
            '<?php $__blaze->popData(); $__env->popConsumableComponentData(); ?>',
        );
    }
}
