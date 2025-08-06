<?php

namespace Livewire\Blaze\Parser;

use Livewire\Blaze\Parser\State;
use Livewire\Blaze\Parser\Nodes\Node;
use Livewire\Blaze\Parser\Nodes\TagNode;
use Livewire\Blaze\Parser\Nodes\TextNode;
use Livewire\Blaze\Parser\Nodes\SlotNode;
use Livewire\Blaze\Parser\Tokens\Token;
use Livewire\Blaze\Parser\Tokens\TextToken;
use Livewire\Blaze\Parser\Tokens\TagOpenToken;
use Livewire\Blaze\Parser\Tokens\TagSelfCloseToken;
use Livewire\Blaze\Parser\Tokens\TagCloseToken;
use Livewire\Blaze\Parser\Tokens\SlotOpenToken;
use Livewire\Blaze\Parser\Tokens\SlotCloseToken;

class Parser
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
    protected string $currentPrefix = '';
    protected string $currentSlotPrefix = '';

    function tokenize(string $content): array
    {
        $tokens = [];
        $state = State::TEXT;
        $buffer = '';
        $currentToken = null;
        $i = 0;
        $len = strlen($content);
        $tagStack = [];

        while ($i < $len) {
            $char = $content[$i];

            switch ($state) {
                case State::TEXT:
                    // Check for slot tags first to ensure they take precedence over component tags
                    $foundSlotPrefix = false;
                    foreach ($this->prefixes as $prefix => $config) {
                        $slotPrefix = $config['slot'];
                        if ($char === '<' && substr($content, $i, strlen($slotPrefix) + 1) === '<' . $slotPrefix) {
                            if (trim($buffer) !== '') {
                                $tokens[] = new TextToken($buffer);
                                $buffer = '';
                            }
                            // Check for short slot syntax (<x-slot:name>)
                            if ($i + strlen($slotPrefix) + 1 < $len && $content[$i + strlen($slotPrefix) + 1] === ':') {
                                $state = State::SHORT_SLOT;
                                $currentToken = new SlotOpenToken(slotStyle: 'short');
                                $this->currentSlotPrefix = $slotPrefix;
                                $i += strlen($slotPrefix) + 2; // Skip <x-slot:
                            } else {
                                $state = State::SLOT;
                                $currentToken = new SlotOpenToken(slotStyle: 'standard');
                                $this->currentSlotPrefix = $slotPrefix;
                                $i += strlen($slotPrefix);
                            }
                            $foundSlotPrefix = true;
                            break;
                        }
                    }

                    if ($foundSlotPrefix) {
                        break;
                    }

                    // Check for slot closing tags
                    $foundSlotClose = false;
                    foreach ($this->prefixes as $prefix => $config) {
                        $slotPrefix = $config['slot'];
                        if ($char === '<' && substr($content, $i, 2) === '</' &&
                                substr($content, $i, strlen($slotPrefix) + 2) === '</' . $slotPrefix) {
                            if (trim($buffer) !== '') {
                                $tokens[] = new TextToken($buffer);
                                $buffer = '';
                            }
                            $state = State::SLOT_CLOSE;
                            $currentToken = new SlotCloseToken();
                            $this->currentSlotPrefix = $slotPrefix;
                            $i += strlen($slotPrefix) + 2;
                            $foundSlotClose = true;
                            break;
                        }
                    }

                    if ($foundSlotClose) {
                        break;
                    }

                    // Check for opening tags with any of the prefixes
                    if ($char === '<') {
                        $foundPrefix = false;
                        foreach ($this->prefixes as $prefix => $config) {
                            if (substr($content, $i, strlen($prefix) + 1) === '<' . $prefix) {
                                if (trim($buffer) !== '') {
                                    $tokens[] = new TextToken($buffer);
                                    $buffer = '';
                                }
                                $state = State::TAG_OPEN;
                                $this->currentPrefix = $prefix;
                                $currentToken = new TagOpenToken(
                                    name: '',
                                    prefix: $prefix,
                                    namespace: $config['namespace'] ?? ''
                                );
                                $i += strlen($prefix) + 1;
                                $foundPrefix = true;
                                break;
                            }
                        }

                        if ($foundPrefix) {
                            break;
                        }

                        // Check for closing tags with any of the prefixes
                        if (substr($content, $i, 2) === '</') {
                            $foundPrefix = false;
                            foreach ($this->prefixes as $prefix => $config) {
                                if (substr($content, $i, strlen($prefix) + 2) === '</' . $prefix) {
                                    if (trim($buffer) !== '') {
                                        $tokens[] = new TextToken($buffer);
                                        $buffer = '';
                                    }
                                    $state = State::TAG_CLOSE;
                                    $this->currentPrefix = $prefix;
                                    $currentToken = new TagCloseToken(
                                        name: '',
                                        prefix: $prefix,
                                        namespace: $config['namespace'] ?? ''
                                    );
                                    $i += strlen($prefix) + 2;
                                    $foundPrefix = true;
                                    break;
                                }
                            }

                            if ($foundPrefix) {
                                break;
                            }
                        }

                        // Regular HTML tag - just add to buffer
                        $buffer .= $char;
                        $i++;
                    } else {
                        $buffer .= $char;
                        $i++;
                    }
                    break;

                case State::TAG_OPEN:
                    if (preg_match('/^[a-zA-Z0-9-\.:]+/', substr($content, $i), $matches)) {
                        $currentToken->name = $matches[0];
                        $tagStack[] = $matches[0];
                        $i += strlen($matches[0]);
                        $state = State::ATTRIBUTE_NAME;
                    } else {
                        $i++;
                    }
                    break;

                case State::TAG_CLOSE:
                    if (preg_match('/^[a-zA-Z0-9-\.:]+/', substr($content, $i), $matches)) {
                        $currentToken->name = $matches[0];
                        array_pop($tagStack);
                        $i += strlen($matches[0]);
                        if ($i < $len && $content[$i] === '>') {
                            $tokens[] = $currentToken;
                            $state = State::TEXT;
                            $i++;
                        }
                    } else {
                        $i++;
                    }
                    break;

                case State::ATTRIBUTE_NAME:
                    if ($char === ' ') {
                        $i++;
                    } elseif ($char === '>' && $this->isTagEndMarker($content, $i)) {
                        $tokens[] = $currentToken;
                        $state = State::TEXT;
                        $i++;
                    } elseif ($char === '/' && $i + 1 < $len && $content[$i + 1] === '>' && $this->isTagEndMarker($content, $i)) {
                        // Convert TagOpenToken to TagSelfCloseToken
                        $currentToken = new TagSelfCloseToken(
                            name: $currentToken->name,
                            prefix: $currentToken->prefix,
                            namespace: $currentToken->namespace,
                            attributes: $currentToken->attributes
                        );
                        array_pop($tagStack);
                        $tokens[] = $currentToken;
                        $state = State::TEXT;
                        $i += 2;
                    } else {
                        // Start collecting the entire attribute string
                        $attrString = '';

                        // Track quote and nesting states
                        $inSingleQuote = false;
                        $inDoubleQuote = false;
                        $braceCount = 0;
                        $bracketCount = 0;
                        $parenCount = 0;

                        while ($i < $len) {
                            $currentChar = $content[$i];

                            // Track quote state
                            if ($currentChar === '"' && !$inSingleQuote && ($i === 0 || $content[$i-1] !== '\\')) {
                                $inDoubleQuote = !$inDoubleQuote;
                            } elseif ($currentChar === "'" && !$inDoubleQuote && ($i === 0 || $content[$i-1] !== '\\')) {
                                $inSingleQuote = !$inSingleQuote;
                            }

                            // Track braces/brackets/parentheses only when not in quotes
                            if (!$inSingleQuote && !$inDoubleQuote) {
                                if ($currentChar === '{') $braceCount++;
                                elseif ($currentChar === '}') $braceCount--;
                                elseif ($currentChar === '[') $bracketCount++;
                                elseif ($currentChar === ']') $bracketCount--;
                                elseif ($currentChar === '(') $parenCount++;
                                elseif ($currentChar === ')') $parenCount--;
                            }

                            // Check for end of tag, but only if we're not inside quotes or nested structures
                            if (($currentChar === '>' ||
                                ($currentChar === '/' && $i + 1 < $len && $content[$i + 1] === '>')) &&
                                !$inSingleQuote && !$inDoubleQuote &&
                                $braceCount === 0 && $bracketCount === 0 && $parenCount === 0) {
                                break;
                            }

                            $attrString .= $currentChar;
                            $i++;
                        }

                        $currentToken->attributes = trim($attrString);
                        $state = State::ATTRIBUTE_NAME;
                    }
                    break;

                case State::SLOT:
                    if ($char === ' ') {
                        $i++;
                    } elseif (preg_match('/^name="([^"]+)"/', substr($content, $i), $matches)) {
                        $currentToken->name = $matches[1];
                        $i += strlen($matches[0]);
                        $state = State::ATTRIBUTE_NAME;
                    } elseif ($char === '>') {
                        $tokens[] = $currentToken;
                        $state = State::TEXT;
                        $i++;
                    } else {
                        $i++;
                    }
                    break;

                case State::SLOT_CLOSE:
                    if (preg_match('/^:[a-zA-Z0-9-]+/', substr($content, $i), $matches)) {
                        $currentToken->name = substr($matches[0], 1); // Remove the colon
                        $i += strlen($matches[0]);
                    }

                    if ($i < $len && $content[$i] === '>') {
                        $tokens[] = $currentToken;
                        $state = State::TEXT;
                        $i++;
                    } else {
                        $i++;
                    }
                    break;

                case State::SHORT_SLOT:
                    if (preg_match('/^[a-zA-Z0-9-]+/', substr($content, $i), $matches)) {
                        $currentToken->name = $matches[0];
                        $i += strlen($matches[0]);

                        // Now collect attributes if they exist
                        $attrBuffer = '';
                        while ($i < $len && $content[$i] !== '>') {
                            $attrBuffer .= $content[$i];
                            $i++;
                        }

                        if (trim($attrBuffer) !== '') {
                            $currentToken->attributes = trim($attrBuffer);
                        }

                        if ($i < $len && $content[$i] === '>') {
                            $tokens[] = $currentToken;
                            $state = State::TEXT;
                            $i++;
                        }
                    } else {
                        $i++;
                    }
                    break;
            }
        }

        if (trim($buffer) !== '') {
            $tokens[] = new TextToken($buffer);
        }

        return $tokens;
    }

    public function parse(array $tokens): array
    {
        $ast = [];
        $stack = [];
        $currentNode = &$ast;

        foreach ($tokens as $token) {
            if ($token instanceof TagOpenToken) {
                $node = new TagNode(
                    name: $token->namespace . $token->name,
                    prefix: $token->prefix,
                    attributes: $token->attributes,
                    children: [],
                    selfClosing: false
                );

                $currentNode[] = $node;
                $stack[] = &$currentNode;
                $currentNode = &$currentNode[array_key_last($currentNode)]->children;
            } elseif ($token instanceof TagSelfCloseToken) {
                $currentNode[] = new TagNode(
                    name: $token->namespace . $token->name,
                    prefix: $token->prefix,
                    attributes: $token->attributes,
                    children: [],
                    selfClosing: true
                );
            } elseif ($token instanceof TagCloseToken) {
                $currentNode = &$stack[array_key_last($stack)];
                array_pop($stack);
            } elseif ($token instanceof SlotOpenToken) {
                $node = new SlotNode(
                    name: $token->name ?? '',
                    attributes: $token->attributes,
                    slotStyle: $token->slotStyle,
                    children: []
                );

                $currentNode[] = $node;
                $stack[] = &$currentNode;
                $currentNode = &$currentNode[array_key_last($currentNode)]->children;
            } elseif ($token instanceof SlotCloseToken) {
                $currentNode = &$stack[array_key_last($stack)];
                array_pop($stack);
            } elseif ($token instanceof TextToken) {
                if (trim($token->content) !== '') {
                    $currentNode[] = new TextNode(
                        content: $token->content
                    );
                }
            }
        }

        return $ast;
    }

    public function transform(array $ast, callable $callback, bool $postOrder = false): array
    {
        $transformNode = function ($node, $tagLevel = 0) use ($callback, $postOrder, &$transformNode) {
            if (!($node instanceof Node)) return $node;

            // Pre-order traversal: transform parent before children
            if (!$postOrder) {
                $transformed = $callback($node, $tagLevel);
                if ($transformed === null) return null;
                if (!($transformed instanceof Node)) return $transformed;
                $node = $transformed;
            }

            // Transform children
            if (($node instanceof TagNode || $node instanceof SlotNode) && !empty($node->children)) {
                $node->children = array_filter(
                    array_map(
                        fn($child) => $transformNode($child, $node instanceof TagNode ? $tagLevel + 1 : $tagLevel),
                        $node->children
                    ),
                    fn($child) => $child !== null
                );
            }

            // Post-order traversal: transform parent after children
            if ($postOrder) {
                $transformed = $callback($node, $tagLevel);
                if ($transformed === null) return null;
                if (!($transformed instanceof Node)) return $transformed;
                $node = $transformed;
            }

            return $node;
        };

        return array_filter(
            array_map(fn($node) => $transformNode($node, 0), $ast),
            fn($node) => $node !== null
        );
    }

    public function render(array $ast): string
    {
        $output = '';

        foreach ($ast as $node) {
            if ($node instanceof TagNode) {
                $prefix = $node->prefix ?? $this->prefixes[0];
                // Strip namespace from name when rendering
                $name = $node->name;
                if (isset($this->prefixes[$prefix]['namespace']) && str_starts_with($name, $this->prefixes[$prefix]['namespace'])) {
                    $name = substr($name, strlen($this->prefixes[$prefix]['namespace']));
                }
                $output .= "<{$prefix}{$name}";

                // Render attributes as a string if present
                if (!empty($node->attributes)) {
                    $output .= " {$node->attributes}";
                }

                if ($node->selfClosing) {
                    $output .= " />";
                } else {
                    $output .= ">";
                    $output .= $this->render($node->children);
                    $output .= "</{$prefix}{$name}>";
                }
            } elseif ($node instanceof SlotNode) {
                if ($node->slotStyle === 'short') {
                    // Use short slot syntax
                    $output .= "<{$this->currentSlotPrefix}:{$node->name}";

                    // Add attributes if present
                    if (!empty($node->attributes)) {
                        $output .= " {$node->attributes}";
                    }

                    $output .= ">";
                    $output .= $this->render($node->children);
                    $output .= "</{$this->currentSlotPrefix}:{$node->name}>";
                } else {
                    // Use standard slot syntax
                    $output .= "<{$this->currentSlotPrefix} name=\"{$node->name}\"";

                    // Add other attributes if present (excluding the name which we've already added)
                    if (!empty($node->attributes)) {
                        // Since we're using name="value" syntax explicitly, we need to avoid duplicating name attribute
                        if (preg_match('/^class="([^"]*)"/', $node->attributes, $matches)) {
                            $output .= " class=\"{$matches[1]}\"";
                        } else if (trim($node->attributes) !== '') {
                            $output .= " " . $node->attributes;
                        }
                    }

                    $output .= ">";
                    $output .= $this->render($node->children);
                    $output .= "</{$this->currentSlotPrefix}>";
                }
            } elseif ($node instanceof TextNode) {
                $output .= $node->content;
            }
        }

        return $output;
    }

    public function isStaticNode(Node $node): bool
    {
        if (!($node instanceof TagNode)) {
            return false;
        }

        // Check if attributes contain dynamic expressions
        if (!empty($node->attributes)) {
            // Check if attributes string contains : attribute or {{ expressions
            if (preg_match('/(^|\s):[a-zA-Z]/', $node->attributes) || str_contains($node->attributes, '{'.'{')) {
                return false;
            }
        }

        // Check that all children are text nodes
        foreach ($node->children as $child) {
            if (!($child instanceof TextNode)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if this is a genuine tag end marker, not inside quotes or code
     *
     * @param string $content The full content string being parsed
     * @param int $position The current position in the string
     * @return bool
     */
    protected function isTagEndMarker(string $content, int $position): bool
    {
        // This is a simpler implementation since we're already checking for this elsewhere
        // The full check would need to track quotes and nested structures
        // which we do inline in the attribute parsing
        return $position === 0 || preg_match('/[\s\w"]/', $content[$position-1]);
    }
}