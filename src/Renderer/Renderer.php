<?php

namespace Livewire\Blaze\Renderer;

use Livewire\Blaze\Nodes\Node;
use Livewire\Blaze\Nodes\ComponentNode;
use Livewire\Blaze\Nodes\TextNode;
use Livewire\Blaze\Nodes\SlotNode;

class Renderer
{
    protected array $prefixes = [
        'flux:' => [
            'namespace' => 'flux::',
            'slot' => 'x-slot',
        ],
        'x:' => [
            'namespace' => '',
            'slot' => 'x-slot',
        ],
        'x-' => [
            'namespace' => '',
            'slot' => 'x-slot',
        ],
    ];

    protected string $currentSlotPrefix = '';

    public function render(array $ast): string
    {
        return implode('', array_map([$this, 'renderNode'], $ast));
    }

    public function renderNode(Node $node): string
    {
        return match(get_class($node)) {
            ComponentNode::class => $this->renderComponent($node),
            SlotNode::class => $this->renderSlot($node),
            TextNode::class => $node->content,
            default => throw new \RuntimeException('Unknown node type: ' . get_class($node))
        };
    }

    protected function renderComponent(ComponentNode $node): string
    {
        $output = $this->buildOpeningTag($node);

        if ($node->selfClosing) {
            return $output . ' />';
        }

        $output .= '>';
        $output .= $this->render($node->children);
        $output .= $this->buildClosingTag($node);

        return $output;
    }

    protected function renderSlot(SlotNode $node): string
    {
        // Determine the slot prefix to use
        $this->currentSlotPrefix = $this->determineSlotPrefix($node);

        if ($node->slotStyle === 'short') {
            return $this->renderShortSlot($node);
        }

        return $this->renderStandardSlot($node);
    }

    protected function buildOpeningTag(ComponentNode $node): string
    {
        $prefix = $node->prefix;
        $name = $this->stripNamespaceFromName($node->name, $prefix);

        $output = "<{$prefix}{$name}";

        if (!empty($node->attributes)) {
            $output .= " {$node->attributes}";
        }

        return $output;
    }

    protected function buildClosingTag(ComponentNode $node): string
    {
        $prefix = $node->prefix;
        $name = $this->stripNamespaceFromName($node->name, $prefix);

        return "</{$prefix}{$name}>";
    }

    protected function renderShortSlot(SlotNode $node): string
    {
        $slotPrefix = $this->currentSlotPrefix ?: 'x-slot';
        $output = "<{$slotPrefix}:{$node->name}";

        if (!empty($node->attributes)) {
            $output .= " {$node->attributes}";
        }

        $output .= ">";
        $output .= $this->render($node->children);
        $output .= "</{$slotPrefix}:{$node->name}>";

        return $output;
    }

    protected function renderStandardSlot(SlotNode $node): string
    {
        $slotPrefix = $this->currentSlotPrefix ?: 'x-slot';
        $output = "<{$slotPrefix}";

        if (!empty($node->name)) {
            $output .= ' name="' . $node->name . '"';
        }

        if (!empty($node->attributes)) {
            $output .= " {$node->attributes}";
        }

        $output .= ">";
        $output .= $this->render($node->children);
        $output .= "</{$slotPrefix}>";

        return $output;
    }

    protected function stripNamespaceFromName(string $name, string $prefix): string
    {
        // Find the matching prefix configuration
        if (isset($this->prefixes[$prefix])) {
            $namespace = $this->prefixes[$prefix]['namespace'];
            if (!empty($namespace) && str_starts_with($name, $namespace)) {
                return substr($name, strlen($namespace));
            }
        }

        return $name;
    }

    protected function determineSlotPrefix(SlotNode $node): string
    {
        // This is a simplification - in the real implementation,
        // we would track context from parent components
        return 'x-slot';
    }
}