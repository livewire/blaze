<?php

namespace Livewire\Blaze\Folder;

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

    protected array $data = [];
    protected array $attributes = [];
    protected array $awareData = [];
    protected array $slots = [];
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
        $this->setupAttributes();
        $this->setupSlots();

        $this->html = $this->renderer->render($this->path, $this->data, $this->attributes, $this->slots, $this->awareData);
        
        $this->processUncompiledAttributes();
        $this->restorePlaceholders();
        $this->wrapWithAwareMacros();

        return $this->html;
    }

    /**
     * Prepare component and inherited attributes for isolated rendering.
     */
    protected function setupAttributes(): void
    {
        foreach ($this->node->attributes as $attribute) {
            $value = $this->prepareAttribute($attribute);

            $this->data[$attribute->propName] = $value;
            $this->attributes[$attribute->name] = $value;
        }

        foreach ($this->node->parentsAttributes as $attribute) {
            $this->awareData[$attribute->name] = $this->prepareAttribute($attribute);
        }
    }

    /**
     * Get the value of an attribute for isolated rendering, using a placeholder for a dynamic value.
     */
    protected function prepareAttribute(Attribute $attribute): mixed
    {
        if ($attribute->isStaticValue()) {
            return $attribute->getStaticValue();
        }

        $placeholder = 'BLAZE_PLACEHOLDER_' . $this->placeholderIndex++ . '_';

        $this->attributeByPlaceholder[$placeholder] = $attribute;

        return $placeholder;
    }

    /**
     * Replace slot children with placeholders for rendering, storing originals for restoration.
     */
    protected function setupSlots(): void
    {
        $looseContent = [];

        foreach ($this->node->children as $child) {
            if ($child instanceof SlotNode) {
                $this->slots[$child->name] = '';

                if ($this->hasActualContent($child->children)) {
                    $placeholder = 'BLAZE_PLACEHOLDER_' . $this->placeholderIndex++ . '_';

                    $this->slotByPlaceholder[$placeholder] = $child;

                    $this->slots[$child->name] = $placeholder;
                }
            } else {
                $looseContent[] = $child;
            }
        }

        // Synthesize a default slot from loose content when there's not an explicit one
        if ($this->hasActualContent($looseContent) && ! isset($this->slots['slot'])) {
            $placeholder = 'BLAZE_PLACEHOLDER_' . $this->placeholderIndex++ . '_';

            $this->slotByPlaceholder[$placeholder] = new SlotNode(
                name: 'slot',
                attributeString: '',
                slotStyle: 'standard',
                children: $looseContent,
                prefix: 'x-slot',
            );

            $this->slots['slot'] = $placeholder;
        }
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
        $this->html = preg_replace_callback('/\[BLAZE_ATTR:(BLAZE_PLACEHOLDER_[0-9]+_):(.+?)\](\r?\n)?/', function ($matches) {
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
        // Attribute placeholders inside PHP blocks need raw values
        $this->html = preg_replace_callback('/<\?php.*?\?>/s', function ($match) {
            $content = $match[0];

            foreach ($this->attributeByPlaceholder as $placeholder => $attribute) {
                $value = $attribute->bound() ? $attribute->value : $this->blade->compileAttributeEchos($attribute->value);

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
