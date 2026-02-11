<?php

namespace Livewire\Blaze\Folder;

use Closure;
use Illuminate\Support\Str;
use Livewire\Blaze\Nodes\ComponentNode;
use Livewire\Blaze\Nodes\SlotNode;
use Livewire\Blaze\Nodes\TextNode;
use Livewire\Blaze\Support\ComponentSource;
use Livewire\Blaze\Nodes\Attribute;
use Livewire\Blaze\Support\Utils;
use Livewire\Blaze\BladeService;

class Foldable
{
    protected array $attributeByPlaceholder = [];
    protected array $slotByPlaceholder = [];

    protected ComponentNode $renderable;
    protected string $html;

    public function __construct(
        protected ComponentNode $node,
        protected ComponentSource $source,
        protected Closure $renderBlade,
    ) {
    }

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

    protected function setupAttributes(): void
    {
        foreach ($this->node->attributes as $key => $attribute) {
            if (! $attribute->isStaticValue()) {
                // Replace dynamic attributes with placeholders...
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

        // If no explicit default slot, create a synthetic one from loose content
        // Laravel behavior: explicit default slot takes precedence over loose content
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

    protected function mergeAwareProps(): void
    {
        $aware = $this->source->directives->array('aware') ?? [];
        
        foreach ($aware as $prop => $default) {
            if (is_int($prop)) {
                $prop = $default;
                $default = null;
            }

            $this->renderable->attributes[$prop] ??= $this->node->parentsAttributes[$prop] ?? new Attribute(
                name: $prop,
                value: $default,
                propName: $prop,
                prefix: null,
                dynamic: false,
                quotes: '"',
            );
        }
    }

    protected function processUncompiledAttributes(): void
    {
        $this->html = preg_replace_callback('/\[BLAZE_ATTR:(BLAZE_PLACEHOLDER_[A-Z0-9]+)\]/', function ($matches) {
            $placeholder = $matches[1];
            $attribute = $this->attributeByPlaceholder[$placeholder];

            if ($attribute->bound()) {
                // Laravel sets value of all boolean attributes to its name, except for x-data and wire...
                $booleanValue = ($attribute->name === 'x-data' || str_starts_with($attribute->name, 'wire:')) ? "''" : "'".addslashes($attribute->name)."'";

                return '<'.'?php if (($__blazeAttr = '.$attribute->value.') !== false && !is_null($__blazeAttr)): ?'.'>'
                .' '.$attribute->name.'="<'.'?php echo e($__blazeAttr === true ? '.$booleanValue.' : $__blazeAttr); ?'.'>"'
                .'<'.'?php endif; unset($__blazeAttr); ?'.'>';
            } else {
                // Dynamic non-bound attributes with {{ expressions }} will never be false or null,
                // therefore we can just return the attribute name and value as is.
                return $attribute->name.'="'.$attribute->value.'"';
            }
        }, $this->html);
    }

    protected function restorePlaceholders(): void
    {
        // TODO: What is slots are outputted as variables? {{ $footer }}

        // Replace placeholders inside PHP blocks...
        $this->html = preg_replace_callback('/<\?php.*?\?>/s', function ($match) {
            $content = $match[0];

            foreach ($this->attributeByPlaceholder as $placeholder => $attribute) {
                $value = $attribute->bound() ? $attribute->value : Utils::compileAttributeEchos($attribute->value);

                $content = str_replace("'" . $placeholder . "'", $value, $content);
            }

            return $content;
        }, $this->html);

        // Replace remaining placeholders in HTML context with Blade echos...
        foreach ($this->attributeByPlaceholder as $placeholder => $attribute) {
            $value = $attribute->bound() ? '{{ ' . $attribute->value . ' }}' : $attribute->value;

            $this->html = str_replace($placeholder, $value, $this->html);
        }

        // Replace slot placeholders with their original content...
        foreach ($this->slotByPlaceholder as $placeholder => $slot) {
            $this->html = str_replace($placeholder, trim($slot->content()), $this->html);
        }
    }

    protected function wrapWithAwareMacros(): void
    {
        if (! $this->renderable->attributes) {
            return;
        }

        if (! $this->hasAwareDescendant($this->node)) {
            return;
        }

        // To enable @aware to work in non-Blaze child components,
        // we'll add a php block that pushes this component's data
        // onto the @aware stack when the component is rendered.

        $data = [];

        foreach ($this->renderable->attributes as $attribute) {
            $attribute = $this->attributeByPlaceholder[$attribute->value] ?? $attribute;

            if ($attribute->bound()) {
                $data[] = var_export($attribute->propName, true).' => '.$attribute->value;
            } else {
                $data[] = var_export($attribute->propName, true).' => '.Utils::compileAttributeEchos($attribute->value);
            }
        }

        $this->html = Str::wrap($this->html,
            '<?php $__env->pushConsumableComponentData(['.implode(', ', $data).']); ?>',
            '<?php $__env->popConsumableComponentData(); ?>',
        );
    }
    
    protected function hasAwareDescendant(ComponentNode | SlotNode $node): bool
    {
        $children = [];

        $children = $node->children;

        foreach ($children as $child) {
            if ($child instanceof ComponentNode) {
                $source = new ComponentSource($child->name);

                if (! $source->exists()) {
                    continue;
                }

                if ($source->directives->has('aware')) {
                    return true;
                }

                if ($this->hasAwareDescendant($child)) {
                    return true;
                }
            } elseif ($child instanceof SlotNode) {
                if ($this->hasAwareDescendant($child)) {
                    return true;
                }
            }
        }

        return false;
    }
}