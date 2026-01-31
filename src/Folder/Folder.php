<?php

namespace Livewire\Blaze\Folder;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Livewire\Blaze\Compiler\ArrayParser;
use Livewire\Blaze\Compiler\DirectiveMatcher;
use Livewire\Blaze\Directive\BlazeDirective;
use Livewire\Blaze\Events\ComponentFolded;
use Livewire\Blaze\Exceptions\InvalidBlazeFoldUsageException;
use Livewire\Blaze\Exceptions\LeftoverPlaceholdersException;
use Livewire\Blaze\Nodes\ComponentNode;
use Livewire\Blaze\Nodes\Node;
use Livewire\Blaze\Nodes\SlotNode;
use Livewire\Blaze\Nodes\TextNode;
use Livewire\Blaze\Support\AttributeParser;

class Folder
{
    protected $renderBlade;

    protected $renderNodes;

    protected $componentNameToPath;

    protected $getOptimizeBuilder;

    public function __construct(
        callable $renderBlade,
        callable $renderNodes,
        callable $componentNameToPath,
        callable $getOptimizeBuilder,
    ) {
        $this->renderBlade = $renderBlade;
        $this->renderNodes = $renderNodes;
        $this->componentNameToPath = $componentNameToPath;
        $this->getOptimizeBuilder = $getOptimizeBuilder;
    }

    public function isFoldable(Node $node): bool
    {
        if (! $node instanceof ComponentNode) {
            return false;
        }

        try {
            $componentPath = ($this->componentNameToPath)($node->name);

            if (empty($componentPath) || ! file_exists($componentPath)) {
                return false;
            }

            $source = file_get_contents($componentPath);

            $directiveParameters = BlazeDirective::getParameters($source);

            // Get path-based default for fold
            $optimizeBuilder = ($this->getOptimizeBuilder)();
            $pathFoldDefault = $optimizeBuilder->shouldFold($componentPath);

            // Component-level @blaze(fold: ...) takes priority over path config
            if (! is_null($directiveParameters) && isset($directiveParameters['fold'])) {
                return $directiveParameters['fold'];
            }

            // Use path-based default if available
            if ($pathFoldDefault !== null) {
                return $pathFoldDefault;
            }

            // Final fallback: false (folding is opt-in)
            return $directiveParameters['fold'] ?? false;

        } catch (\Exception $e) {
            return false;
        }
    }

    public function fold(Node $node): Node
    {
        if (! $node instanceof ComponentNode) {
            return $node;
        }

        if (! $this->isFoldable($node)) {
            return $node;
        }

        /** @var ComponentNode $component */
        $component = $node;

        try {
            $componentPath = ($this->componentNameToPath)($component->name);

            $source = file_get_contents($componentPath);

            $this->validateFoldableComponent($source, $componentPath);

            $directiveParameters = BlazeDirective::getParameters($source);

            // Default to true if aware parameter is not specified
            if ($directiveParameters['aware'] ?? true) {
                $awareAttributes = $this->getAwareDirectiveAttributes($source);

                if (! empty($awareAttributes)) {
                    $component->mergeAwareAttributes($awareAttributes);
                }
            }

            [$processedNode, $slotPlaceholders, $restore, $attributeNameToPlaceholder, $attributeNameToOriginal, $rawAttributes] = $component->replaceDynamicPortionsWithPlaceholders(
                renderNodes: fn (array $nodes) => ($this->renderNodes)($nodes)
            );

            // Get the list of props that are safe/unsafe to be dynamic
            $safeList = $directiveParameters['safe'] ?? [];
            $unsafeList = $directiveParameters['unsafe'] ?? [];

            // Check for wildcard - all dynamic values are safe
            $allSafe = in_array('*', $safeList);

            // Detect :$attributes spread - always abort folding
            // The attributes bag can't be evaluated at compile time
            if ($this->hasAttributesSpread($rawAttributes)) {
                return $component;
            }

            // Get the list of props defined in @props directive
            // Only dynamic attributes that ARE defined props should prevent folding
            // Dynamic attributes NOT in @props go to $attributes and shouldn't abort folding
            $definedProps = $this->getDefinedProps($source);

            // Filter dynamic attributes to only those that should prevent folding
            $unsafeBoundAttributes = array_filter(
                $attributeNameToOriginal,
                fn ($value, $name) => $this->isUnsafeDynamicAttribute($name, $definedProps, $safeList, $unsafeList),
                ARRAY_FILTER_USE_BOTH
            );

            // Check for bound attributes (:prop), short attributes (:$prop), and echo attributes (prop="{{ ... }}")
            // Only abort folding if there are unsafe dynamic props that are defined in @props
            $hasUnsafeBoundAttributes = ! empty($unsafeBoundAttributes);
            $hasEchoAttributes = $this->hasEchoInAttributes($rawAttributes, $safeList, $unsafeList, $definedProps);
            $hasUnsafeSlots = $this->hasUnsafeSlots($slotPlaceholders, $unsafeList);

            if ($hasUnsafeBoundAttributes || $hasEchoAttributes || $hasUnsafeSlots) {
                return $component; // Fall back to standard Blade
            }

            $usageBlade = ($this->renderNodes)([$processedNode]);

            app('blaze')->startFolding();
            try {
                $renderedHtml = ($this->renderBlade)($usageBlade);
            } finally {
                app('blaze')->stopFolding();
            }

            $finalHtml = $restore($renderedHtml);

            $shouldInjectAwareMacros = $this->hasAwareDescendant($component);

            if ($shouldInjectAwareMacros) {
                $dataArrayLiteral = $this->buildRuntimeDataArray($attributeNameToOriginal, $rawAttributes);

                if ($dataArrayLiteral !== '[]') {
                    $finalHtml = '<?php $__env->pushConsumableComponentData('.$dataArrayLiteral.'); ?>'.$finalHtml.'<?php $__env->popConsumableComponentData(); ?>';
                }
            }

            if ($this->containsLeftoverPlaceholders($finalHtml)) {
                $summary = $this->summarizeLeftoverPlaceholders($finalHtml);

                throw new LeftoverPlaceholdersException($component->name, $summary, substr($finalHtml, 0, 2000));
            }

            Event::dispatch(new ComponentFolded(
                name: $component->name,
                path: $componentPath,
                filemtime: filemtime($componentPath)
            ));

            return new TextNode($finalHtml);

        } catch (InvalidBlazeFoldUsageException $e) {
            throw $e;
        } catch (\Exception $e) {
            // if (app('blaze')->isDebugging()) {
            //     throw $e;
            // }

            return $component;
        }
    }

    protected function validateFoldableComponent(string $source, string $componentPath): void
    {
        // Strip out @unblaze blocks before validation since they can contain dynamic content
        $sourceWithoutUnblaze = $this->stripUnblazeBlocks($source);

        $problematicPatterns = [
            '@once' => 'forOnce',
            '\\$errors' => 'forErrors',
            'session\\(' => 'forSession',
            '@error\\(' => 'forError',
            '@csrf' => 'forCsrf',
            'auth\\(\\)' => 'forAuth',
            'request\\(\\)' => 'forRequest',
            'old\\(' => 'forOld',
        ];

        foreach ($problematicPatterns as $pattern => $factoryMethod) {
            if (preg_match('/'.$pattern.'/', $sourceWithoutUnblaze)) {
                throw InvalidBlazeFoldUsageException::{$factoryMethod}($componentPath);
            }
        }
    }

    protected function stripUnblazeBlocks(string $source): string
    {
        // Remove content between @unblaze and @endunblaze (including the directives themselves)
        return preg_replace('/@unblaze.*?@endunblaze/s', '', $source);
    }

    /**
     * Check if attributes contain echo syntax within attribute values.
     *
     * Matches patterns like:
     *  - attribute="value {{ $var }}"
     *  - attribute="{{ $var }}"
     *  - attribute="prefix {{ $var }} suffix"
     *
     * Does NOT match:
     *  - Bound attributes (:attribute="$var")
     *  - Text outside attributes that happens to contain {{
     *  - Attributes that are in the safeList
     *  - Attributes that are not defined in @props (unless in unsafeList)
     */
    protected function hasEchoInAttributes(string $attributes, array $safeList = [], array $unsafeList = [], array $definedProps = []): bool
    {
        if (empty($attributes)) {
            return false;
        }

        // Wildcard - all dynamic values are safe
        if (in_array('*', $safeList)) {
            return false;
        }

        // Find all attributes with echo syntax
        // This regex matches: attribute="...{{...}}..."
        if (preg_match_all('/([a-zA-Z0-9_:-]+)\s*=\s*"[^"]*\{\{[^}]+\}\}[^"]*"/', $attributes, $matches)) {
            // Check if any matched attribute should prevent folding
            foreach ($matches[1] as $attributeName) {
                if ($this->isUnsafeDynamicAttribute($attributeName, $definedProps, $safeList, $unsafeList)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if the raw attributes string contains :$attributes spread syntax.
     */
    protected function hasAttributesSpread(string $attributes): bool
    {
        return (bool) preg_match('/(?<!\S):\$attributes\b/', $attributes);
    }

    /**
     * Determine if a dynamic attribute should prevent folding.
     *
     * An attribute is "unsafe" (prevents folding) if:
     * - It is explicitly in the unsafe list, OR
     * - It is a defined prop AND not in the safe list AND safe: ['*'] is not set
     */
    protected function isUnsafeDynamicAttribute(string $name, array $definedProps, array $safeList, array $unsafeList): bool
    {
        // Wildcard - all dynamic values are safe
        if (in_array('*', $safeList)) {
            return false;
        }

        // Explicitly safe - never abort
        if (in_array($name, $safeList)) {
            return false;
        }

        // Explicitly unsafe - always abort
        if (in_array($name, $unsafeList)) {
            return true;
        }

        // Default: only abort if it's a defined prop
        // Attributes NOT in @props go to $attributes and shouldn't abort folding
        return in_array($name, $definedProps);
    }

    /**
     * Check if any slots in the unsafe list have content.
     *
     * Slots are pass-through by default (don't abort folding).
     * Only when a slot is in the unsafe list AND has content should folding abort.
     */
    protected function hasUnsafeSlots(array $slotPlaceholders, array $unsafeList): bool
    {
        if (empty($unsafeList)) {
            return false;
        }

        foreach ($slotPlaceholders as $placeholder => $content) {
            // Determine the slot key
            if (str_starts_with($placeholder, 'SLOT_PLACEHOLDER_')) {
                // Default slot
                $slotKey = 'slot';
            } else {
                // Named slot: NAMED_SLOT_footer -> footer
                $slotKey = str_replace('NAMED_SLOT_', '', $placeholder);
            }

            // If slot is in unsafe list AND has content, abort folding
            if (in_array($slotKey, $unsafeList) && ! empty(trim($content))) {
                return true;
            }
        }

        return false;
    }

    protected function buildRuntimeDataArray(array $attributeNameToOriginal, string $rawAttributes): string
    {
        $pairs = [];

        // Dynamic attributes -> original expressions...
        foreach ($attributeNameToOriginal as $name => $original) {
            $key = $this->toCamelCase($name);

            if (preg_match('/\{\{\s*\$([a-zA-Z0-9_]+)\s*\}\}/', $original, $m)) {
                $pairs[$key] = '$'.$m[1];
            } else {
                $pairs[$key] = var_export($original, true);
            }
        }

        // Static attributes from the original attribute string...
        if (! empty($rawAttributes)) {
            if (preg_match_all('/\b([a-zA-Z0-9_-]+)="([^"]*)"/', $rawAttributes, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $name = $m[1];
                    $value = $m[2];
                    $key = $this->toCamelCase($name);
                    if (! isset($pairs[$key])) {
                        $pairs[$key] = var_export($value, true);
                    }
                }
            }
        }

        if (empty($pairs)) {
            return '[]';
        }

        $parts = [];
        foreach ($pairs as $name => $expr) {
            $parts[] = var_export($name, true).' => '.$expr;
        }

        return '['.implode(', ', $parts).']';
    }

    protected function getAwareDirectiveAttributes(string $source): array
    {
        preg_match('/@aware\(\[(.*?)\]\)/s', $source, $matches);

        if (empty($matches[1])) {
            return [];
        }

        $attributeParser = new AttributeParser;

        return $attributeParser->parseArrayStringIntoArray($matches[1]);
    }

    /**
     * Extract the list of defined prop names from the component source.
     *
     * Returns an array of prop names including both camelCase and kebab-case variants
     * since attributes can be passed in either format.
     */
    protected function getDefinedProps(string $source): array
    {
        $matcher = new DirectiveMatcher;
        $expression = $matcher->extractExpression($source, 'props');

        if ($expression === null) {
            return [];
        }

        try {
            $parser = new ArrayParser;
            $items = $parser->parse($expression);
        } catch (\Exception $e) {
            return [];
        }

        $props = [];

        foreach (array_keys($items) as $name) {
            $props[] = $name;

            // Also include kebab-case variant since attributes can be passed that way
            $kebab = Str::kebab($name);
            if ($kebab !== $name) {
                $props[] = $kebab;
            }
        }

        return $props;
    }

    protected function hasAwareDescendant(Node $node): bool
    {
        $children = [];

        if ($node instanceof ComponentNode || $node instanceof SlotNode) {
            $children = $node->children;
        }

        foreach ($children as $child) {
            if ($child instanceof ComponentNode) {
                $path = ($this->componentNameToPath)($child->name);

                if ($path && file_exists($path)) {
                    $source = file_get_contents($path);

                    if (preg_match('/@aware/', $source)) {
                        return true;
                    }
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

    protected function toCamelCase(string $name): string
    {
        $name = str_replace(['-', '_'], ' ', $name);

        $name = ucwords($name);

        $name = str_replace(' ', '', $name);

        return lcfirst($name);
    }

    protected function containsLeftoverPlaceholders(string $html): bool
    {
        return (bool) preg_match('/\b(SLOT_PLACEHOLDER_\d+|ATTR_PLACEHOLDER_\d+|NAMED_SLOT_[A-Za-z0-9_-]+)\b/', $html);
    }

    protected function summarizeLeftoverPlaceholders(string $html): string
    {
        preg_match_all('/\b(SLOT_PLACEHOLDER_\d+|ATTR_PLACEHOLDER_\d+|NAMED_SLOT_[A-Za-z0-9_-]+)\b/', $html, $matches);

        $counts = array_count_values($matches[1] ?? []);

        $parts = [];

        foreach ($counts as $placeholder => $count) {
            $parts[] = $placeholder.' x'.$count;
        }

        return implode(', ', $parts);
    }
}
