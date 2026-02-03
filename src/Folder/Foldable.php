<?php

namespace Livewire\Blaze\Folder;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Livewire\Blaze\Nodes\ComponentNode;
use Livewire\Blaze\Nodes\TextNode;
use Livewire\Blaze\Support\ComponentSource;
use Livewire\Blaze\Nodes\Attribute;
use Livewire\Blaze\Support\Utils;

class Foldable
{
    protected array $replacements = [];
    protected array $attributeNameByPlaceholder = [];
    protected ComponentNode $renderable;

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
        $this->mergeAwareAttributes();

        $html = ($this->renderBlade)($this->renderable->render());
        $html = $this->processUncompiledAttributes($html);
        $html = $this->restorePlaceholders($html);
        $html = $this->wrapWithAwareMacros($html);

        return $html;
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

            // TODO: Slot rendering should ideally involve just joining the children together...
            $this->replacements[$placeholder] = $slot->render();

            if ($slot->children) {
                $slot->children = [new TextNode($placeholder)];
            }
        }
    }

    protected function mergeAwareAttributes(): void
    {
        $aware = $this->source->directives->array('aware') ?? [];
        
        foreach ($aware as $prop => $default) {
            $this->renderable->attributeString[$prop] ??= new Attribute(
                name: $prop,
                value: $component->parentAttributes[$prop] ?? $default,
                prefix: null,
                dynamic: false,
            );
        }
    }

    protected function processUncompiledAttributes($content): string
    {
        return preg_replace_callback('/\[BLAZE_ATTR:(BLAZE_PLACEHOLDER_[A-Z0-9]+)\]/', function ($matches) {
            $placeholder = $matches[1];
            $name = $this->attributeNameByPlaceholder[$placeholder];
            $value = Utils::compileAttributeEchos($this->replacements[$placeholder]);

            // Laravel sets value of all boolean attributes to its name, except for x-data and wire...
            $booleanValue = ($name === 'x-data' || str_starts_with($name, 'wire:')) ? "''" : "'".addslashes($name)."'";

            return '<'.'?php if (($__blazeAttr = '.$value.') !== false && !is_null($__blazeAttr)): ?'.'>'
             .' '.$name.'="<'.'?php echo e($__blazeAttr === true ? '.$booleanValue.' : $__blazeAttr); ?'.'>"'
             .'<'.'?php endif; unset($__blazeAttr); ?'.'>';
        }, $content);
    }

    protected function restorePlaceholders($content): string
    {
        foreach ($this->replacements as $placeholder => $value) {
            $content = str_replace($placeholder, $value, $content);
        }

        return $content;
    }

    protected function wrapWithAwareMacros($content): string
    {
        if (! $this->hasAwareDescendants()) {
            return $content;
        }
        
        $attributes = Arr::mapWithKeys($this->renderable->attributes, function ($attribute) {
            $name = Str::camel($attribute->name);
            $value = $this->replacements[$attribute->value] ?? $attribute->value;
            $value = Utils::compileAttributeEchos($value);
            
            return [$name => $value];
        });

        // Is var_export the best solution for this?
        return '<?php $__env->pushConsumableComponentData('.var_export($attributes, true).'); ?>'
                .$content
                .'<?php $__env->popConsumableComponentData(); ?>';
    }
    
    protected function hasAwareDescendants(): bool
    {
        // TODO: This should be a recursive check...
        foreach ($this->renderable->children as $child) {
            if ($child instanceof ComponentNode) {
                $source = new ComponentSource($child->name);

                return $source->directives->has('aware');
            }
        }

        return false;
    }
}