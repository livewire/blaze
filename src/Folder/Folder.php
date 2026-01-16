<?php

namespace Livewire\Blaze\Folder;

use Livewire\Blaze\Exceptions\LeftoverPlaceholdersException;
use Livewire\Blaze\Exceptions\InvalidBlazeFoldUsageException;
use Livewire\Blaze\Support\AttributeParser;
use Livewire\Blaze\Events\ComponentFolded;
use Livewire\Blaze\Nodes\ComponentNode;
use Illuminate\Support\Facades\Event;
use Livewire\Blaze\Nodes\TextNode;
use Livewire\Blaze\Nodes\SlotNode;
use Livewire\Blaze\Nodes\Node;
use Livewire\Blaze\Directive\BlazeDirective;

class Folder
{
    protected $renderBlade;
    protected $renderNodes;
    protected $componentNameToPath;
    protected DynamicUsageAnalyzer $analyzer;

    public function __construct(
        callable $renderBlade,
        callable $renderNodes,
        callable $componentNameToPath,
        DynamicUsageAnalyzer $analyzer = new DynamicUsageAnalyzer,
    ) {
        $this->renderBlade = $renderBlade;
        $this->renderNodes = $renderNodes;
        $this->componentNameToPath = $componentNameToPath;
        $this->analyzer = $analyzer;
    }

    public function isFoldable(Node $node): bool
    {
        if (! $node instanceof ComponentNode) {
            return false;
        }

        try {
            $componentPath = ($this->componentNameToPath)($node->name);

            if (empty($componentPath) || !file_exists($componentPath)) {
                return false;
            }

            $source = file_get_contents($componentPath);

            $directiveParameters = BlazeDirective::getParameters($source);

            if (is_null($directiveParameters)) {
                return false;
            }

            // Default to true if fold parameter is not specified
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

            // Check if dynamic props can be safely folded (including nested component analysis)
            $dynamicPropNames = array_keys($attributeNameToOriginal);
            $dynamicSlotNames = $this->extractSlotNames($component);

            if ((! empty($dynamicPropNames) || ! empty($dynamicSlotNames)) && ! $this->analyzer->canFold(
                source: $source,
                dynamicAttributes: $dynamicPropNames,
                componentNameToPath: $this->componentNameToPath,
                dynamicSlots: $dynamicSlotNames,
            )) {
                return $component; // Fall back to standard Blade
            }

            $usageBlade = ($this->renderNodes)([$processedNode]);

            $renderedHtml = ($this->renderBlade)($usageBlade);

            $finalHtml = $restore($renderedHtml);

            $shouldInjectAwareMacros = $this->hasAwareDescendant($component);

            if ($shouldInjectAwareMacros) {
                $dataArrayLiteral = $this->buildRuntimeDataArray($attributeNameToOriginal, $rawAttributes);

                if ($dataArrayLiteral !== '[]') {
                    $finalHtml = '<?php $__env->pushConsumableComponentData(' . $dataArrayLiteral . '); ?>' . $finalHtml . '<?php $__env->popConsumableComponentData(); ?>';
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
            if (app('blaze')->isDebugging()) {
                throw $e;
            }

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
            if (preg_match('/' . $pattern . '/', $sourceWithoutUnblaze)) {
                throw InvalidBlazeFoldUsageException::{$factoryMethod}($componentPath);
            }
        }
    }

    protected function stripUnblazeBlocks(string $source): string
    {
        // Remove content between @unblaze and @endunblaze (including the directives themselves)
        return preg_replace('/@unblaze.*?@endunblaze/s', '', $source);
    }

    protected function buildRuntimeDataArray(array $attributeNameToOriginal, string $rawAttributes): string
    {
        $pairs = [];

        // Dynamic attributes -> original expressions...
        foreach ($attributeNameToOriginal as $name => $original) {
            $key = $this->toCamelCase($name);

            if (preg_match('/\{\{\s*\$([a-zA-Z0-9_]+)\s*\}\}/', $original, $m)) {
                $pairs[$key] = '$' . $m[1];
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

        if (empty($pairs)) return '[]';

        $parts = [];
        foreach ($pairs as $name => $expr) {
            $parts[] = var_export($name, true) . ' => ' . $expr;
        }

        return '[' . implode(', ', $parts) . ']';
    }

    protected function getAwareDirectiveAttributes(string $source): array
    {
        preg_match('/@aware\(\[(.*?)\]\)/s', $source, $matches);

        if (empty($matches[1])) {
            return [];
        }

        $attributeParser = new AttributeParser();

        return $attributeParser->parseArrayStringIntoArray($matches[1]);
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

    /**
     * Extract slot variable names from a component's children.
     *
     * @return array Slot variable names (e.g., ['slot', 'header', 'footer'])
     */
    protected function extractSlotNames(ComponentNode $component): array
    {
        $slotNames = [];
        $hasDefaultSlotContent = false;

        foreach ($component->children as $child) {
            if ($child instanceof SlotNode) {
                $name = $child->name;
                // Named slot (non-empty name that isn't 'slot')
                if (! empty($name) && $name !== 'slot') {
                    $slotNames[] = $name;
                } else {
                    // Default slot via <x-slot> or <x-slot name="slot">
                    $hasDefaultSlotContent = true;
                }
            } else {
                // Any non-SlotNode child is default slot content
                // But skip empty text nodes
                if ($child instanceof TextNode && trim($child->content) === '') {
                    continue;
                }
                $hasDefaultSlotContent = true;
            }
        }

        if ($hasDefaultSlotContent) {
            $slotNames[] = 'slot';
        }

        return $slotNames;
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
            $parts[] = $placeholder . ' x' . $count;
        }

        return implode(', ', $parts);
    }
}
