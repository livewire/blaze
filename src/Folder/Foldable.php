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
    protected array $replacements = [];
    protected array $attributeNameByPlaceholder = [];
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
        $this->renderable = clone $this->node;

        $this->replaceAttributesWithPlaceholders();
        $this->replaceSlotsWithPlaceholders();
        $this->mergeAwareAttributesFromParents();

        $this->html = BladeService::render($this->renderable->render());
        
        $this->processUncompiledAttributes();
        $this->restorePlaceholders();
        $this->wrapWithAwareMacros();

        return $this->html;
    }

    protected function replaceAttributesWithPlaceholders(): void
    {
        foreach ($this->renderable->attributes as $attribute) {
            if ($attribute->dynamic) {
                $placeholder = 'BLAZE_PLACEHOLDER_' . strtoupper(str()->random());

                $this->replacements[$placeholder] = $attribute->value;
                $this->attributeNameByPlaceholder[$placeholder] = $attribute->name;

                $attribute->value = $placeholder;
            }
        }
    }

    protected function replaceSlotsWithPlaceholders(): void
    {
        foreach ($this->renderable->slots as $slot) {
            $placeholder = 'BLAZE_PLACEHOLDER_' . strtoupper(str()->random());

            $this->replacements[$placeholder] = $slot->render();

            if ($slot->children) {
                $slot->children = [new TextNode($placeholder)];
            }
        }
    }

    protected function mergeAwareAttributesFromParents(): void
    {
        $aware = $this->source->directives->array('aware');
        
        foreach ($aware as $prop => $default) {
            $this->renderable->attributes[$prop] ??= $this->node->parentsAttributes[$prop] ?? new Attribute(
                name: $prop,
                value: $default,
                prefix: null,
                dynamic: false,
            );
        }
    }

    protected function processUncompiledAttributes(): void
    {
        preg_replace_callback('/\[BLAZE_ATTR:(BLAZE_PLACEHOLDER_[A-Z0-9]+)\]/', function ($matches) {
            $placeholder = $matches[1];
            $name = $this->attributeNameByPlaceholder[$placeholder];
            $value = Utils::compileAttributeEchos($this->replacements[$placeholder]);

            // Laravel sets value of all boolean attributes to its name, except for x-data and wire...
            $booleanValue = ($name === 'x-data' || str_starts_with($name, 'wire:')) ? "''" : "'".addslashes($name)."'";

            return '<'.'?php if (($__blazeAttr = '.$value.') !== false && !is_null($__blazeAttr)): ?'.'>'
             .' '.$name.'="<'.'?php echo e($__blazeAttr === true ? '.$booleanValue.' : $__blazeAttr); ?'.'>"'
             .'<'.'?php endif; unset($__blazeAttr); ?'.'>';
        }, $this->html);
    }

    protected function restorePlaceholders(): void
    {
        foreach ($this->replacements as $placeholder => $value) {
            $this->html = str_replace($placeholder, $value, $this->html);
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
            if (isset($this->replacements[$attribute->value])) {
                // We need to compile {{  }} syntax into string concatenation for dynamic attributes...
                $data[] = var_export($attribute->name, true).' => '.Utils::compileAttributeEchos($this->replacements[$attribute->value]);
            } else {
                $data[] = var_export($attribute->name, true).' => '.var_export($attribute->value, true);
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