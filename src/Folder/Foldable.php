<?php

namespace Livewire\Blaze\Folder;

use Illuminate\Support\Str;
use Livewire\Blaze\BladeService;
use Livewire\Blaze\Nodes\Attribute;
use Livewire\Blaze\Nodes\ComponentNode;
use Livewire\Blaze\Nodes\SlotNode;
use Livewire\Blaze\Nodes\TextNode;
use Livewire\Blaze\Support\ComponentSource;
use Livewire\Blaze\Support\Utils;

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

    protected ComponentNode $renderable;
    protected string $html;

    public function __construct(
        protected ComponentNode $node,
        protected ComponentSource $source,
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
            attributeString: '',
            children: [],
            selfClosing: $this->node->selfClosing,
            parentsAttributes: $this->node->parentsAttributes,
        );

        $this->setupAttributes();
        $this->setupSlots();
        $this->mergeAwareProps();

        $this->html = BladeService::render($this->renderable->render());
        
        $this->processUncompiledAttributes();
        $this->restorePlaceholders();
        $this->wrapWithAwareMacros();

        return $this->html;
    }

    /**
     * Replace dynamic attributes with placeholders, keep static ones as-is.
     */
    protected function setupAttributes(): void
    {
        foreach ($this->node->attributes as $key => $attribute) {
            if (! $attribute->isStaticValue()) {
                $placeholder = 'BLAZE_PLACEHOLDER_' . strtoupper(str()->random());

                $this->attributeByPlaceholder[$placeholder] = $attribute;

                $this->renderable->attributes[$key] = new Attribute(
                    name: $attribute->name,
                    value: $placeholder,
                    propName: $attribute->propName,
                    prefix: '',
                    dynamic: false,
                    quotes: '"',
                );
            } else {
                $this->renderable->attributes[$key] = clone $attribute;
            }
        }
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
                $placeholder = 'BLAZE_PLACEHOLDER_' . strtoupper(str()->random());

                $this->slotByPlaceholder[$placeholder] = $child;

                $slots[$child->name] = new SlotNode(
                    name: $child->name,
                    attributeString: $child->attributeString,
                    slotStyle: $child->slotStyle,
                    children: [new TextNode($placeholder)],
                    prefix: $child->prefix,
                    closeHasName: $child->closeHasName,
                );
            } else {
                $looseContent[] = $child;
            }
        }

        // Synthesize a default slot from loose content when there's not an explicit one
        if ($looseContent && ! isset($slots['slot'])) {
            $placeholder = 'BLAZE_PLACEHOLDER_' . strtoupper(str()->random());

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
     * Merge @aware-declared props from parent attributes into the renderable node.
     */
    protected function mergeAwareProps(): void
    {
        $aware = $this->source->directives->array('aware') ?? [];
        
        foreach ($aware as $prop => $default) {
            if (is_int($prop)) {
                $prop = $default;
                $default = null;
            }
            
            if (isset($this->node->parentsAttributes[$prop])) {
                $this->renderable->attributes[$prop] ??= $this->node->parentsAttributes[$prop];
            } elseif ($default !== null) {
                $this->renderable->attributes[$prop] ??= new Attribute(
                    name: $prop,
                    value: $default,
                    propName: $prop,
                    prefix: null,
                    dynamic: false,
                    quotes: '"',
                );
            } else {
                // When default is null and no parent provides a value, we intentionally
                // skip adding the attribute. This lets @aware and @props handle defaults
                // at runtime, matching the non-folded behavior. Adding an attribute with
                // null value would render as prop="" in HTML, corrupting null to empty string.
            }
        }
    }

    /**
     * Convert [BLAZE_ATTR:...] markers into conditional PHP for dynamic attributes.
     */
    protected function processUncompiledAttributes(): void
    {
        $this->html = preg_replace_callback('/\[BLAZE_ATTR:(BLAZE_PLACEHOLDER_[A-Z0-9]+)\]/', function ($matches) {
            $attribute = $this->attributeByPlaceholder[$matches[1]];

            if ($attribute->bound()) {
                // x-data and wire:* get empty string for true, others get key name
                $booleanValue = ($attribute->name === 'x-data' || str_starts_with($attribute->name, 'wire:')) ? "''" : "'".addslashes($attribute->name)."'";

                return '<'.'?php if (($__blazeAttr = '.$attribute->value.') !== false && !is_null($__blazeAttr)): ?'.'>'
                . $attribute->name.'="<'.'?php echo e($__blazeAttr === true ? '.$booleanValue.' : $__blazeAttr); ?'.'>"'
                .'<'.'?php endif; unset($__blazeAttr); ?'.'>';
            } else {
                return $attribute->name.'="'.$attribute->value.'"';
            }
        }, $this->html);
    }

    /**
     * Replace all placeholders with their original dynamic values or slot content.
     */
    protected function restorePlaceholders(): void
    {
        // Attribute placeholders inside PHP blocks need raw values
        $this->html = preg_replace_callback('/<\?php.*?\?>/s', function ($match) {
            $content = $match[0];

            foreach ($this->attributeByPlaceholder as $placeholder => $attribute) {
                $value = $attribute->bound() ? $attribute->value : Utils::compileAttributeEchos($attribute->value);

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
            $this->html = str_replace($placeholder, trim($slot->content()), $this->html);
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
            if ($attribute->bound()) {
                $data[] = var_export($attribute->propName, true).' => '.$attribute->value;
            } else {
                $data[] = var_export($attribute->propName, true).' => '.Utils::compileAttributeEchos($attribute->value);
            }
        }

        $dataString = implode(', ', $data);

        $this->html = Str::wrap($this->html,
            '<?php $__blaze->pushData(['.$dataString.']); $__env->pushConsumableComponentData(['.$dataString.']); ?>',
            '<?php $__blaze->popData(); $__env->popConsumableComponentData(); ?>',
        );
    }
}