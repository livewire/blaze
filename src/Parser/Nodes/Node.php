<?php

namespace Livewire\Blaze\Parser\Nodes;

/**
 * Base class for all AST nodes in the Blaze pipeline.
 */
abstract class Node
{
    /**
     * Render this node to its string output.
     */
    abstract public function render(): string;

    public function containsPhp(string $php): bool
    {
        // TODO: for TextNode we should check variables inside {{  }}
        if ($this instanceof PhpBlockNode || $this instanceof TextNode) {
            if (str_contains($this->content, $php)) {
                return true;
            }
        }

        if ($this instanceof ComponentNode || $this instanceof SlotNode) {
            if (str_contains($this->attributeString, $php)) {
                return true;
            }
        }

        if ($this instanceof DirectiveNode) {
            if (str_contains($this->expression, $php)) {
                return true;
            }
        }

        return false;
    }

    public function usesEchoSyntax(): bool
    {
        if ($this instanceof TextNode) {
            if (preg_match('/\{\{.+?\}\}|\{!!.+?!!\}/s', $this->content) === 1) {
                return true;
            }
        }

        if ($this instanceof ComponentNode || $this instanceof SlotNode) {
            if (preg_match('/\{\{.+?\}\}|\{!!.+?!!\}/s', $this->attributeString) === 1) {
                return true;
            }
        }

        return false;
    }

    public function isDirective(string|array $name): bool
    {
        $names = is_array($name) ? $name : [$name];
        $names = array_map(fn ($s) => strtolower($s), $names);

        return $this instanceof DirectiveNode && in_array(strtolower($this->name), $names);
    }
}
