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

        /** @var ComponentNode $component */
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

            // Let the ComponentNode prepare placeholders and a processed usage node
            [$processedNode, $slotPlaceholders, $restore] = $component->replaceDynamicPortionsWithPlaceholders(
                renderNodes: fn (array $nodes) => ($this->renderNodes)($nodes)
            );

            // Convert the processed node (component usage) back to a Blade tag string
            $usageBlade = ($this->renderNodes)([$processedNode]);

            // Render the Blade usage through Blade's renderer (sandboxed externally)
            $renderedHtml = ($this->renderBlade)($usageBlade);

            // Restore placeholders back to original content
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
