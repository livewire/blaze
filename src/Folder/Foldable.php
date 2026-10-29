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

        $placeholder = new Placeholder($this->placeholderIndex++, bound: $attribute->bound());

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
     */
    protected function processUncompiledAttributes(): void
    {
        $this->html = preg_replace_callback('/\[BLAZE_ATTR:(' . Placeholder::PATTERN . '):(.+?)\](\r?\n)?/', function ($matches) {
            [, $attribute] = $this->attributeFor($matches[1]);
            $name = $matches[2];

            if (! $attribute) {
                return $matches[0];
            }

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
        // Attribute placeholders inside PHP blocks sit in string literals, e.g. the exported scope of
        // @unblaze. A literal made of placeholders and text becomes a concatenation of their expressions,
        // raw or escaped as many times as the placeholder was while rendering
        $this->html = preg_replace_callback('/<\?php.*?\?>/s', function ($match) {
            return preg_replace_callback("/'((?:[^'\\\\]|\\\\.)*)'/", function ($matches) {
                if (! str_contains($matches[1], 'BLAZE_PLACEHOLDER_')) {
                    return $matches[0];
                }

                $parts = preg_split('/(' . Placeholder::PATTERN . ')/', $matches[1], -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

                return implode(' . ', array_map(function ($part) {
                    [$placeholder, $attribute] = $this->attributeFor($part);

                    return match (true) {
                        ! $attribute => "'" . $part . "'",
                        $attribute->bound() => $this->escapedExpression($attribute->value, $placeholder),
                        default => $this->blade->compileAttributeEchos($attribute->value),
                    };
                }, $parts));
            }, $match[0]);
        }, $this->html);

        // Attribute placeholders in HTML context need Blade echo syntax
        $this->html = preg_replace_callback('/' . Placeholder::PATTERN . '/', function ($matches) {
            [, $attribute] = $this->attributeFor($matches[0]);

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
     * Resolve a placeholder to the attribute it stands for.
     *
     * A bound attribute's placeholder is only recognized with its "&_" marker intact. Without it,
     * the component transformed the value, so it is left in place and fails the fold.
     *
     * @return array{0: ?Placeholder, 1: ?Attribute}
     */
    protected function attributeFor(string $value): array
    {
        $placeholder = Placeholder::parse($value);
        $attribute = $placeholder ? $this->attributeByIndex[$placeholder->index] ?? null : null;

        if ($attribute && $attribute->bound() !== $placeholder->bound) {
            return [null, null];
        }

        return [$placeholder, $attribute];
    }

    /**
     * Fail the fold when placeholders or attribute markers remain, so the component is compiled without folding.
     */
    protected function ensureNoLeftoverPlaceholders(): void
    {
        if (preg_match_all('/BLAZE_PLACEHOLDER_[0-9]+_\S*|\[BLAZE_ATTR:[^\]]*\]/', $this->html, $matches)) {
            throw new LeftoverPlaceholdersException(
                $this->node->name,
                implode(', ', array_unique($matches[0])),
                Str::limit($this->html, 500),
            );
        }
    }

    /**
     * Get the expression of a bound value escaped as many times as its placeholder was while rendering.
     *
     * sanitizeComponentAttribute() keeps booleans and null like the attribute bag and merge() do.
     */
    protected function escapedExpression(string $value, Placeholder $placeholder): string
    {
        $sanitize = '\\Illuminate\\View\\Compilers\\BladeCompiler::sanitizeComponentAttribute(';

        return str_repeat($sanitize, $placeholder->escapes) . $value . str_repeat(')', $placeholder->escapes);
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
