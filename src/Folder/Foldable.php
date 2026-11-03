<?php

namespace Livewire\Blaze\Folder;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Livewire\Blaze\BladeRenderer;
use Livewire\Blaze\BladeService;
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
    protected array $attributeByPlaceholder = [];
    protected array $slotByPlaceholder = [];
    protected int $placeholderIndex = 0;

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
        $data = Arr::mapWithKeys($this->node->attributes, fn (Attribute $attribute) => [
            $attribute->propName => $this->storeAttribute($attribute, 'PROP'),
        ]);

        $attributes = Arr::mapWithKeys($this->node->attributes, fn (Attribute $attribute) => [
            $attribute->name => $this->storeAttribute($attribute, 'ATTR'),
        ]);

        $awareData = Arr::mapWithKeys($this->node->parentsAttributes, fn (Attribute $attribute) => [
            $attribute->name => $this->storeAttribute($attribute, 'PROP'),
        ]);

        $slots = Arr::map($this->getSlots(), fn (SlotNode $slot) => $this->storeSlot($slot));

        $this->html = $this->renderer->render($this->path, $data, $attributes, $slots, $awareData);

        $this->processUncompiledAttributes();
        $this->restorePlaceholders();
        $this->wrapWithAwareMacros();

        return $this->html;
    }

    /**
     * Store a dynamic attribute behind a placeholder, or return the value of a static one as is.
     */
    protected function storeAttribute(Attribute $attribute, string $type): mixed
    {
        if ($attribute->isStaticValue()) {
            return $attribute->getStaticValue();
        }

        $index = $this->placeholderIndex++;
        $placeholder = 'BLAZE_PLACEHOLDER_' . $type . '_' . $index . '_';

        $this->attributeByPlaceholder[$placeholder] = $attribute;

        // Escaping a prop while folding turns it into an attribute placeholder with the same index
        // @see \Livewire\Blaze\Runtime\BlazeRuntime::sanitizeFoldedAttribute()
        if ($type === 'PROP') {
            $this->attributeByPlaceholder['BLAZE_PLACEHOLDER_ATTR_' . $index . '_'] = $attribute;
        }

        return $placeholder;
    }

    /**
     * Store a slot's content behind a placeholder, or return an empty string if it has none.
     */
    protected function storeSlot(SlotNode $slot): string
    {
        if (! $this->hasActualContent($slot->children)) {
            return '';
        }

        $placeholder = 'BLAZE_PLACEHOLDER_' . $this->placeholderIndex++ . '_';

        $this->slotByPlaceholder[$placeholder] = $slot;

        return $placeholder;
    }

    /**
     * Get the component's slots by name, with loose content as the default slot if there's no explicit one.
     */
    protected function getSlots(): array
    {
        $slots = [];
        $looseContent = [];

        foreach ($this->node->children as $child) {
            if ($child instanceof SlotNode) {
                $slots[$child->name] = $child;
            } else {
                $looseContent[] = $child;
            }
        }

        if ($this->hasActualContent($looseContent) && ! isset($slots['slot'])) {
            $slots['slot'] = new SlotNode(
                name: 'slot',
                attributeString: '',
                slotStyle: 'standard',
                children: $looseContent,
                prefix: 'x-slot',
            );
        }

        return $slots;
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
        $this->html = preg_replace_callback('/\[BLAZE_ATTR:(BLAZE_PLACEHOLDER_(?:PROP_|ATTR_)?[0-9]+_):(.+?)\](\r?\n)?/', function ($matches) {
            $attribute = $this->attributeByPlaceholder[$matches[1]];
            $name = $matches[2];

            if ($attribute->bound()) {
                // x-data and wire:* get empty string for true, others get key name
                $booleanValue = ($name === 'x-data' || str_starts_with($name, 'wire:')) ? "''" : "'".addslashes($name)."'";

                return '<'.'?php if (($__blazeAttr = '.$attribute->value.') !== false && !is_null($__blazeAttr)): ?'.'>'
                . $name.'="<'.'?php echo e($__blazeAttr === true ? '.$booleanValue.' : $__blazeAttr); ?'.'>"'
                .'<'.'?php endif; unset($__blazeAttr); ?'.'>' . (isset($matches[3]) ? $matches[3] . $matches[3] : '');
            } else {
                return $name.'="'.$attribute->value.'"';
            }
        }, $this->html);
    }

    /**
     * Replace all placeholders with their original dynamic values or slot content.
     */
    protected function restorePlaceholders(): void
    {
        // Attribute placeholders inside PHP blocks, like @unblaze scopes, need PHP expressions
        $this->html = preg_replace_callback('/<\?php.*?\?>/s', function ($match) {
            $content = $match[0];

            foreach ($this->attributeByPlaceholder as $placeholder => $attribute) {
                $value = $attribute->bound() ? $attribute->value : $this->blade->compileAttributeEchos($attribute->value);

                // Like Blade, the attribute bag holds bound attributes escaped
                if ($attribute->bound() && str_starts_with($placeholder, 'BLAZE_PLACEHOLDER_ATTR_')) {
                    $value = '\Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(' . $value . ')';
                }

                $content = str_replace("'" . $placeholder . "'", $value, $content);
            }

            return $content;
        }, $this->html);

        // Attribute placeholders in HTML context need Blade echo syntax
        foreach ($this->attributeByPlaceholder as $placeholder => $attribute) {
            $value = $attribute->bound() ? '{{ ' . $attribute->value . ' }}' : $attribute->value;

            $this->html = str_replace($placeholder, $value, $this->html);
        }

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
     * Wrap output with pushConsumableComponentData calls if descendants use @aware.
     */
    protected function wrapWithAwareMacros(): void
    {
        if (! $this->node->attributes) {
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
