<?php

namespace Livewire\Blaze\Folder;

use Livewire\Blaze\Nodes\TextNode;
use Livewire\Blaze\Nodes\Node;
use Livewire\Blaze\Nodes\ComponentNode;
use Livewire\Blaze\Nodes\SlotNode;
use Livewire\Blaze\Events\ComponentFolded;
use Livewire\Blaze\Exceptions\InvalidPureUsageException;
use Livewire\Blaze\Exceptions\LeftoverPlaceholdersException;
use Illuminate\Support\Facades\Event;

class Folder
{
    protected $renderBlade;
    protected $renderNodes;
    protected $componentNameToPath;

    public function __construct(callable $renderBlade, callable $renderNodes, callable $componentNameToPath)
    {
        $this->renderBlade = $renderBlade;
        $this->renderNodes = $renderNodes;
        $this->componentNameToPath = $componentNameToPath;
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

            return (bool) preg_match('/^\s*(?:\/\*.*?\*\/\s*)*@pure/s', $source);

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
            if (file_exists($componentPath)) {
                $source = file_get_contents($componentPath);
                $this->validatePureComponent($source, $componentPath);

                Event::dispatch(new ComponentFolded(
                    name: $component->name,
                    path: $componentPath,
                    filemtime: filemtime($componentPath)
                ));
            }

            [$processedNode, $slotPlaceholders, $restore, $attributeNameToPlaceholder, $attributeNameToOriginal, $rawAttributes] = $component->replaceDynamicPortionsWithPlaceholders(
                renderNodes: fn (array $nodes) => ($this->renderNodes)($nodes)
            );

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

            // Guardrail: detect leftover Blaze placeholders in final output
            if ($this->containsLeftoverPlaceholders($finalHtml)) {
                $summary = $this->summarizeLeftoverPlaceholders($finalHtml);
                throw new LeftoverPlaceholdersException($component->name, $summary, substr($finalHtml, 0, 2000));
            }

            return new TextNode($finalHtml);

        } catch (InvalidPureUsageException $e) {
            throw $e;
        } catch (LeftoverPlaceholdersException $e) {
            if (app('blaze')->isDebugging()) {
                throw $e;
            }

            return $component;
        } catch (\Exception $e) {
            return $component;
        }
    }

    protected function validatePureComponent(string $source, string $componentPath): void
    {
        $problematicPatterns = [
            '@aware' => 'forAware',
            '\\$errors' => 'forErrors',
            'session\\(' => 'forSession',
            '@error\\(' => 'forError',
            '@csrf' => 'forCsrf',
            'auth\\(\\)' => 'forAuth',
            'request\\(\\)' => 'forRequest',
            'old\\(' => 'forOld',
        ];

        foreach ($problematicPatterns as $pattern => $factoryMethod) {
            if (preg_match('/' . $pattern . '/', $source)) {
                throw InvalidPureUsageException::{$factoryMethod}($componentPath);
            }
        }
    }

    protected function buildRuntimeDataArray(array $attributeNameToOriginal, string $rawAttributes): string
    {
        $pairs = [];

        // Dynamic attributes -> original expressions
        foreach ($attributeNameToOriginal as $name => $original) {
            $key = $this->toCamelCase($name);
            if (preg_match('/\{\{\s*\$([a-zA-Z0-9_]+)\s*\}\}/', $original, $m)) {
                $pairs[$key] = '$' . $m[1];
            } else {
                $pairs[$key] = var_export($original, true);
            }
        }

        // Static attributes from the original attribute string
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
            $parts[] = $placeholder . ' x' . $count;
        }
        return implode(', ', $parts);
    }
}
