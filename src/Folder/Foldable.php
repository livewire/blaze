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
    /**
     * Matches the placeholder of a bound attribute escaped any number of times, e.g. "BLAZE_PLACEHOLDER_0_&amp;_".
     */
    protected const BOUND_PLACEHOLDER_PATTERN = 'BLAZE_PLACEHOLDER_[0-9]+_&(?:amp;)*_';

    protected array $attributeByPlaceholder = [];
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

        $this->html = $this->renderer->render($this->renderable, $this->path);
        
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

        $placeholder = 'BLAZE_PLACEHOLDER_' . $this->placeholderIndex++ . '_';

        // Like in Blade, a bound value is escaped in the attribute bag but not in the data read by props.
        // Its placeholder ends with "&_" so the escaped copy ("&amp;_") can be told apart from the raw one
        // when it's restored inside PHP code, e.g. an @unblaze scope built from the attribute bag.
        if ($attribute->bound()) {
            $placeholder .= '&_';
        }

        $this->attributeByPlaceholder[$placeholder] = $attribute;

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
                    $placeholder = 'BLAZE_PLACEHOLDER_' . $this->placeholderIndex++ . '_';
    
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
            $placeholder = 'BLAZE_PLACEHOLDER_' . $this->placeholderIndex++ . '_';

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
        $this->html = preg_replace_callback('/\[BLAZE_ATTR:(BLAZE_PLACEHOLDER_[0-9]+_(?:&(?:amp;)*_)?):(.+?)\](\r?\n)?/', function ($matches) {
            $attribute = $this->attributeByPlaceholder[$this->basePlaceholder($matches[1])];
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
        // Attribute placeholders inside PHP blocks need raw values, or escaped ones where the
        // placeholder was escaped while rendering, e.g. when it was read from the attribute bag
        $this->html = preg_replace_callback('/<\?php.*?\?>/s', function ($match) {
            $content = $match[0];

            foreach ($this->attributeByPlaceholder as $placeholder => $attribute) {
                if (! $attribute->bound()) {
                    $content = str_replace("'" . $placeholder . "'", $this->blade->compileAttributeEchos($attribute->value), $content);
                }
            }

            return preg_replace_callback("/'(" . self::BOUND_PLACEHOLDER_PATTERN . ")'/", function ($matches) {
                $attribute = $this->attributeByPlaceholder[$this->basePlaceholder($matches[1])] ?? null;

                if (! $attribute) {
                    return $matches[0];
                }

                return $this->escapedExpression($attribute->value, substr_count($matches[1], 'amp;'));
            }, $content);
        }, $this->html);

        // Attribute placeholders in HTML context need Blade echo syntax
        foreach ($this->attributeByPlaceholder as $placeholder => $attribute) {
            if (! $attribute->bound()) {
                $this->html = str_replace($placeholder, $attribute->value, $this->html);
            }
        }

        $this->html = preg_replace_callback('/' . self::BOUND_PLACEHOLDER_PATTERN . '/', function ($matches) {
            $attribute = $this->attributeByPlaceholder[$this->basePlaceholder($matches[0])] ?? null;

            return $attribute ? '{{ ' . $attribute->value . ' }}' : $matches[0];
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
     * Get the expression of a bound value as it reads after being escaped the given number of times
     * while rendering. The first time is the attribute bag, the others are escaped by the component.
     */
    protected function escapedExpression(string $value, int $times): string
    {
        if ($times === 0) {
            return $value;
        }

        $expression = '\\Illuminate\\View\\Compilers\\BladeCompiler::sanitizeComponentAttribute(' . $value . ')';

        return str_repeat('e(', $times - 1) . $expression . str_repeat(')', $times - 1);
    }

    /**
     * Check if a value is the placeholder of a bound attribute, which the attribute bag gets escaped.
     */
    public static function isBoundPlaceholder(mixed $value): bool
    {
        return is_string($value) && preg_match('/^BLAZE_PLACEHOLDER_[0-9]+_&_$/', $value) === 1;
    }

    /**
     * Fail when a placeholder couldn't be restored, e.g. because the component transformed it,
     * so the component is compiled without folding instead of printing the placeholder.
     */
    protected function ensureNoLeftoverPlaceholders(): void
    {
        if (preg_match_all('/BLAZE_PLACEHOLDER_[0-9]+_(?:&[a-zA-Z;]*_)?/', $this->html, $matches)) {
            throw new LeftoverPlaceholdersException(
                $this->node->name,
                implode(', ', array_unique($matches[0])),
                Str::limit($this->html, 500),
            );
        }
    }

    /**
     * Get the placeholder an escaped copy of a placeholder was made from.
     */
    protected function basePlaceholder(string $placeholder): string
    {
        return preg_replace('/&(?:amp;)*_$/', '&_', $placeholder);
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
