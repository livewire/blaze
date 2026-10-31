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

        $this->ensureNoMergedCopies();
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

        $placeholder = new Placeholder($this->placeholderIndex++, copy: $attribute->bound() ? 'raw' : null);

        $this->attributeByIndex[$placeholder->index] = $attribute;

        return new Attribute(
            name: $attribute->name,
            value: (string) $placeholder,
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
                    $placeholder = (string) new Placeholder($this->placeholderIndex++);
    
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
            $placeholder = (string) new Placeholder($this->placeholderIndex++);

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
     *
     * A bound attribute is rendered from the attribute bag copy, or from the raw copy escaped once,
     * like merge() does with defaults. Both hold what Blade would have in the bag.
     */
    protected function processUncompiledAttributes(): void
    {
        $this->html = preg_replace_callback('/\[BLAZE_ATTR:(' . Placeholder::PATTERN . '):(.+?)\](\r?\n)?/', function ($matches) {
            [$placeholder, $attribute] = $this->attributeFor($matches[1]);
            $name = $matches[2];

            if (! $attribute) {
                return $matches[0];
            }

            if (! $attribute->bound()) {
                return $name.'="'.$attribute->value.'"';
            }

            if (! $placeholder->is('bag') && ! $placeholder->is('raw', escapes: 1)) {
                return $matches[0];
            }

            // x-data and wire:* get empty string for true, others get key name
            $booleanValue = ($name === 'x-data' || str_starts_with($name, 'wire:')) ? "''" : "'".addslashes($name)."'";

            return '<'.'?php if (($__blazeAttr = '.$attribute->value.') !== false && !is_null($__blazeAttr)): ?'.'>'
            . $name.'="<'.'?php echo e($__blazeAttr === true ? '.$booleanValue.' : $__blazeAttr); ?'.'>"'
            .'<'.'?php endif; unset($__blazeAttr); ?'.'>' . (isset($matches[3]) ? $matches[3] . $matches[3] : '');
        }, $this->html);
    }

    /**
     * Replace placeholders in HTML with their original dynamic values or slot content.
     *
     * Placeholders inside PHP blocks are left alone and fail the fold.
     */
    protected function restorePlaceholders(): void
    {
        $this->html = preg_replace_callback('/<\?php.*?\?>(*SKIP)(*FAIL)|' . Placeholder::PATTERN . '/s', function ($matches) {
            [$placeholder, $attribute] = $this->attributeFor($matches[0]);

            return match (true) {
                ! $attribute => $matches[0],
                ! $attribute->bound() => $attribute->value,
                $placeholder->is('raw') => '{!! ' . $attribute->value . ' !!}',
                $placeholder->is('raw', escapes: 1) => '{{ ' . $attribute->value . ' }}',
                $placeholder->is('bag') => '{!! e(' . $attribute->value . ') !!}',
                default => $matches[0],
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
     * Export the scope of an @unblaze block as PHP code.
     *
     * The scope is evaluated while rendering, so a value that is exactly the placeholder of a raw
     * or attribute bag copy is exported as the runtime expression it stands for. Any other value
     * holding a placeholder fails the fold.
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

        [$placeholder, $attribute] = is_string($value) ? $this->attributeFor($value) : [null, null];

        $code = match (true) {
            ! $attribute => var_export($value, true),
            ! $attribute->bound() => $this->blade->compileAttributeEchos($attribute->value),
            $placeholder->is('raw') => $attribute->value,
            $placeholder->is('bag') => '\\Illuminate\\View\\Compilers\\BladeCompiler::sanitizeComponentAttribute(' . $attribute->value . ')',
            default => null,
        };

        if ($code === null || (! $attribute && stripos($code, 'BLAZE_PLACEHOLDER_') !== false)) {
            throw new LeftoverPlaceholdersException($this->node->name, var_export($value, true));
        }

        return $code;
    }

    /**
     * Resolve a placeholder to the attribute it stands for.
     *
     * @return array{0: ?Placeholder, 1: ?Attribute}
     */
    protected function attributeFor(string $value): array
    {
        $placeholder = Placeholder::parse($value);
        $attribute = $placeholder ? $this->attributeByIndex[$placeholder->index] ?? null : null;

        if ($attribute && $attribute->bound() !== (bool) $placeholder->copy) {
            return [null, null];
        }

        return [$placeholder, $attribute];
    }

    /**
     * Fail the fold when copies of the same bound attribute meet in one attribute value.
     *
     * That's usually a merge, like $attributes->class($class), which Blade dedupes or not
     * depending on whether the copies are equal at runtime.
     */
    protected function ensureNoMergedCopies(): void
    {
        preg_match_all('/"[^"]*"/', $this->html, $values);

        foreach ($values[0] as $value) {
            preg_match_all('/BLAZE_PLACEHOLDER_([0-9]+)_(?:raw|bag)&/', $value, $matches);

            if (count($matches[1]) !== count(array_unique($matches[1]))) {
                throw new LeftoverPlaceholdersException($this->node->name, $value, Str::limit($this->html, 500));
            }
        }
    }

    /**
     * Fail the fold when placeholders or attribute markers remain, so the component is compiled without folding.
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
