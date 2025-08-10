<?php

namespace Livewire\Blaze\Folder;

use Livewire\Blaze\Nodes\TextNode;
use Livewire\Blaze\Nodes\Node;
use Livewire\Blaze\Nodes\ComponentNode;
use Livewire\Blaze\Events\ComponentFolded;
use Livewire\Blaze\Exceptions\InvalidPureUsageException;
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

            // Check for @pure directive at the top of the file (allowing for whitespace and comments)...
            return (bool) preg_match('/^\s*(?:\/\*.*?\*\/\s*)*@pure/s', $source);

        } catch (\Exception $e) {
            return false;
        }
    }

    public function fold(Node $node): Node
    {
        // Only handle component nodes...
        if (! $node instanceof ComponentNode) {
            return $node;
        }

        if (! $this->isFoldable($node)) {
            return $node;
        }

        $component = $node;

        try {
            // Dispatch event for component folding...
            $componentPath = ($this->componentNameToPath)($component->name);
            if (file_exists($componentPath)) {
                // Validate @pure usage before folding...
                $source = file_get_contents($componentPath);
                $this->validatePureComponent($source, $componentPath);

                Event::dispatch(new ComponentFolded(
                    name: $component->name,
                    path: $componentPath,
                    filemtime: filemtime($componentPath)
                ));
            }

            // Let the ComponentNode handle placeholdering (attributes + slots)...
            [$processedNode, $slotPlaceholders, $restore, $attributeNameToPlaceholder] = $component->replaceDynamicPortionsWithPlaceholders(
                renderNodes: fn (array $nodes) => ($this->renderNodes)($nodes)
            );

            // Get the component template and substitute named slot variables with placeholders...
            $componentPath = ($this->componentNameToPath)($component->name);
            $templateSource = file_get_contents($componentPath);

            // First replace named/default slot variables in template...
            $templateWithPlaceholders = $this->replaceNamedSlotVariables($templateSource, $slotPlaceholders);

            // Replace occurrences of attribute variables in the template with placeholders...
            foreach ($attributeNameToPlaceholder as $attrName => $placeholder) {
                // Basic Blade echo replacement; keep simple for now...
                $templateWithPlaceholders = str_replace('{{ $' . $attrName . ' }}', $placeholder, $templateWithPlaceholders);
                // Force placeholder inside common merge arrays (e.g., ['type' => $type])
                $templateWithPlaceholders = str_replace("'{$attrName}' => \\$".$attrName, "'{$attrName}' => '{$placeholder}'", $templateWithPlaceholders);
                $templateWithPlaceholders = str_replace('"'.$attrName.'" => $'.$attrName, '"'.$attrName.'" => ' . "'{$placeholder}'", $templateWithPlaceholders);
            }

            // Parse simple static attributes from usage (key="value" or key='value'), excluding bound ones
            $staticAttrs = [];
            $raw = $component->attributes;
            if ($raw !== '') {
                $pattern = '/(^|\s)([A-Za-z0-9:_-]+)\s*=\s*("([^"]*)"|\'([^\']*)\')/';
                if (preg_match_all($pattern, $raw, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $m) {
                        $name = $m[2];
                        if (str_starts_with($name, ':')) continue; // skip bound
                        $val = $m[4] !== '' ? $m[4] : $m[5];
                        $staticAttrs[$name] = $val;
                    }
                }
            }

            // Define slot variables so expressions like $message ?? $slot are safe
            $slotHeader = '';
            // Default slot placeholder (first processed child placeholder or empty)
            $defaultSlotPlaceholder = '';
            foreach ($slotPlaceholders as $key => $val) {
                if (! str_starts_with($key, 'NAMED_SLOT_')) {
                    $defaultSlotPlaceholder = $key;
                    break;
                }
            }
            // Define $slot
            $slotHeader .= "<?php \$slot = '" . $defaultSlotPlaceholder . "'; ?>\n";
            // Define named slots ($header, $footer, etc.)
            foreach ($slotPlaceholders as $key => $val) {
                if (str_starts_with($key, 'NAMED_SLOT_')) {
                    $name = strtolower(str_replace('NAMED_SLOT_', '', $key));
                    $slotHeader .= "<?php \$" . $name . " = '" . $key . "'; ?>\n";
                }
            }

            // Build attribute bag: start with static attrs, then override with dynamic placeholders
            $bagPairs = [];
            foreach ($staticAttrs as $attrName => $value) {
                $bagPairs[$attrName] = $value;
            }
            foreach ($attributeNameToPlaceholder as $attrName => $placeholder) {
                $bagPairs[$attrName] = $placeholder;
            }

            // Emit variables for simple prop-like names (valid PHP identifiers)
            $varHeader = '';
            foreach ($bagPairs as $attrName => $value) {
                if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $attrName)) {
                    $varHeader .= "<?php \$" . $attrName . " = '" . $value . "'; ?>\n";
                }
            }

            $bagArray = implode(', ', array_map(fn($k, $v) => "'{$k}' => '{$v}'", array_keys($bagPairs), array_values($bagPairs)));
            $bagHeader = $bagArray !== ''
                ? "<?php \$attributes = new \\Illuminate\\View\\ComponentAttributeBag([{$bagArray}]); ?>\n"
                : '';

            // Use the pre-processed component template directly as Blade source
            $bladeSource = $slotHeader . $varHeader . $bagHeader . $templateWithPlaceholders;

            // Render the Blade source through Blade's renderer...
            $renderedHtml = ($this->renderBlade)($bladeSource);

            // Restore placeholders back to original content via callback...
            $finalHtml = $restore($renderedHtml);

            // Return a TextNode containing the folded HTML...
            return new TextNode($finalHtml);

        } catch (InvalidPureUsageException $e) {
            // Re-throw validation exceptions...
            throw $e;
        } catch (\Exception $e) {
            throw $e;
            // return $component;
        }
    }

    protected function extractSlotName(string $attributes): ?string
    {
        // Extract name attribute from slot attributes...
        if (preg_match('/name\s*=\s*["\']([^"\']+)["\']/', $attributes, $matches)) {
            return $matches[1];
        }

        return null;
    }

    protected function replaceNamedSlotVariables(string $templateSource, array $slotPlaceholders): string
    {
        foreach ($slotPlaceholders as $placeholder => $content) {
            if (str_starts_with($placeholder, 'NAMED_SLOT_')) {
                $slotName = strtolower(str_replace('NAMED_SLOT_', '', $placeholder));
                // Replace the variable reference in the template with the placeholder...
                $templateSource = str_replace('{{ $' . $slotName . ' }}', $placeholder, $templateSource);
                // Also replace raw variable occurrences inside expressions with quoted placeholders...
                $templateSource = str_replace('$' . $slotName, "'{$placeholder}'", $templateSource);
            } else {
                // This is a regular slot placeholder - replace {{ $slot }} with it...
                $templateSource = str_replace('{{ $slot }}', $placeholder, $templateSource);
                // Also replace raw $slot occurrences (e.g., {{ $message ?? $slot }}) with quoted placeholders...
                $templateSource = str_replace('$slot', "'{$placeholder}'", $templateSource);
            }
        }
        return $templateSource;
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
}
