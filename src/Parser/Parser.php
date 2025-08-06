<?php

namespace Livewire\Blaze\Parser;

class Parser
{
    const STATE_TEXT = 'TEXT';
    const STATE_TAG_OPEN = 'TAG_OPEN';
    const STATE_TAG_NAME = 'TAG_NAME';
    const STATE_ATTRIBUTE_NAME = 'ATTRIBUTE_NAME';
    const STATE_ATTRIBUTE_VALUE = 'ATTRIBUTE_VALUE';
    const STATE_SLOT = 'SLOT';
    const STATE_SLOT_NAME = 'SLOT_NAME';
    const STATE_TAG_CLOSE = 'TAG_CLOSE';
    const STATE_SLOT_CLOSE = 'SLOT_CLOSE';
    const STATE_SHORT_SLOT = 'SHORT_SLOT';

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
        $state = self::STATE_TEXT;
        $buffer = '';
        $currentToken = null;
        $i = 0;
        $len = strlen($content);
        $tagStack = [];

        while ($i < $len) {
            $char = $content[$i];

            switch ($state) {
                case self::STATE_TEXT:
                    // Check for slot tags first to ensure they take precedence over component tags
                    $foundSlotPrefix = false;
                    foreach ($this->prefixes as $prefix => $config) {
                        $slotPrefix = $config['slot'];
                        if ($char === '<' && substr($content, $i, strlen($slotPrefix) + 1) === '<' . $slotPrefix) {
                            if (trim($buffer) !== '') {
                                $tokens[] = ['type' => 'text', 'content' => $buffer];
                                $buffer = '';
                            }
                            // Check for short slot syntax (<x-slot:name>)
                            if ($i + strlen($slotPrefix) + 1 < $len && $content[$i + strlen($slotPrefix) + 1] === ':') {
                                $state = self::STATE_SHORT_SLOT;
                                $currentToken = ['type' => 'slot_open', 'slot_style' => 'short'];
                                $this->currentSlotPrefix = $slotPrefix;
                                $i += strlen($slotPrefix) + 2; // Skip <x-slot:
                            } else {
                                $state = self::STATE_SLOT;
                                $currentToken = ['type' => 'slot_open', 'slot_style' => 'standard'];
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
                                $tokens[] = ['type' => 'text', 'content' => $buffer];
                                $buffer = '';
                            }
                            $state = self::STATE_SLOT_CLOSE;
                            $currentToken = ['type' => 'slot_close'];
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
                                    $tokens[] = ['type' => 'text', 'content' => $buffer];
                                    $buffer = '';
                                }
                                $state = self::STATE_TAG_OPEN;
                                $this->currentPrefix = $prefix;
                                $currentToken = [
                                    'type' => 'tag_open',
                                    'prefix' => $prefix,
                                    'namespace' => $config['namespace'] ?? '',
                                ];
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
                                        $tokens[] = ['type' => 'text', 'content' => $buffer];
                                        $buffer = '';
                                    }
                                    $state = self::STATE_TAG_CLOSE;
                                    $this->currentPrefix = $prefix;
                                    $currentToken = [
                                        'type' => 'tag_close',
                                        'prefix' => $prefix,
                                        'namespace' => $config['namespace'] ?? '',
                                    ];
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

                case self::STATE_TAG_OPEN:
                    if (preg_match('/^[a-zA-Z0-9-\.:]+/', substr($content, $i), $matches)) {
                        $currentToken['name'] = $matches[0];
                        $tagStack[] = $matches[0];
                        $i += strlen($matches[0]);
                        $state = self::STATE_ATTRIBUTE_NAME;
                    } else {
                        $i++;
                    }
                    break;

                case self::STATE_TAG_CLOSE:
                    if (preg_match('/^[a-zA-Z0-9-\.:]+/', substr($content, $i), $matches)) {
                        $currentToken['name'] = $matches[0];
                        array_pop($tagStack);
                        $i += strlen($matches[0]);
                        if ($i < $len && $content[$i] === '>') {
                            $tokens[] = $currentToken;
                            $state = self::STATE_TEXT;
                            $i++;
                        }
                    } else {
                        $i++;
                    }
                    break;

                case self::STATE_ATTRIBUTE_NAME:
                    if ($char === ' ') {
                        $i++;
                    } elseif ($char === '>' && $this->isTagEndMarker($content, $i)) {
                        $tokens[] = $currentToken;
                        $state = self::STATE_TEXT;
                        $i++;
                    } elseif ($char === '/' && $i + 1 < $len && $content[$i + 1] === '>' && $this->isTagEndMarker($content, $i)) {
                        $currentToken['type'] = 'tag_self_close';
                        array_pop($tagStack);
                        $tokens[] = $currentToken;
                        $state = self::STATE_TEXT;
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

                        $currentToken['attributes'] = trim($attrString);
                        $state = self::STATE_ATTRIBUTE_NAME;
                    }
                    break;

                case self::STATE_SLOT:
                    if ($char === ' ') {
                        $i++;
                    } elseif (preg_match('/^name="([^"]+)"/', substr($content, $i), $matches)) {
                        $currentToken['name'] = $matches[1];
                        $i += strlen($matches[0]);
                        $state = self::STATE_ATTRIBUTE_NAME;
                    } elseif ($char === '>') {
                        $tokens[] = $currentToken;
                        $state = self::STATE_TEXT;
                        $i++;
                    } else {
                        $i++;
                    }
                    break;

                case self::STATE_SLOT_CLOSE:
                    if (preg_match('/^:[a-zA-Z0-9-]+/', substr($content, $i), $matches)) {
                        $currentToken['name'] = substr($matches[0], 1); // Remove the colon
                        $i += strlen($matches[0]);
                    }

                    if ($i < $len && $content[$i] === '>') {
                        $tokens[] = $currentToken;
                        $state = self::STATE_TEXT;
                        $i++;
                    } else {
                        $i++;
                    }
                    break;

                case self::STATE_SHORT_SLOT:
                    if (preg_match('/^[a-zA-Z0-9-]+/', substr($content, $i), $matches)) {
                        $currentToken['name'] = $matches[0];
                        $i += strlen($matches[0]);

                        // Now collect attributes if they exist
                        $attrBuffer = '';
                        while ($i < $len && $content[$i] !== '>') {
                            $attrBuffer .= $content[$i];
                            $i++;
                        }

                        if (trim($attrBuffer) !== '') {
                            $currentToken['attributes'] = trim($attrBuffer);
                        }

                        if ($i < $len && $content[$i] === '>') {
                            $tokens[] = $currentToken;
                            $state = self::STATE_TEXT;
                            $i++;
                        }
                    } else {
                        $i++;
                    }
                    break;
            }
        }

        if (trim($buffer) !== '') {
            $tokens[] = ['type' => 'text', 'content' => $buffer];
        }

        return $tokens;
    }

    public function parse(array $tokens): array
    {
        $ast = [];
        $stack = [];
        $currentNode = &$ast;

        foreach ($tokens as $token) {
            switch ($token['type']) {
                case 'tag_open':
                    $node = [
                        'type' => 'tag',
                        'name' => ($token['namespace'] ?? '') . $token['name'],
                        'prefix' => $token['prefix'] ?? $this->prefixes[0],
                        'attributes' => $token['attributes'] ?? '',
                        'children' => [],
                    ];

                    $currentNode[] = $node;
                    $stack[] = &$currentNode;
                    $currentNode = &$currentNode[array_key_last($currentNode)]['children'];
                    break;

                case 'tag_self_close':
                    $currentNode[] = [
                        'type' => 'tag',
                        'name' => ($token['namespace'] ?? '') . $token['name'],
                        'prefix' => $token['prefix'] ?? $this->prefixes[0],
                        'attributes' => $token['attributes'] ?? '',
                        'children' => [],
                        'self_closing' => true,
                    ];
                    break;

                case 'tag_close':
                    $currentNode = &$stack[array_key_last($stack)];
                    array_pop($stack);
                    break;

                case 'slot_open':
                    $node = [
                        'type' => 'slot',
                        'name' => $token['name'],
                        'attributes' => $token['attributes'] ?? '',
                        'slot_style' => $token['slot_style'] ?? 'standard',
                        'children' => [],
                    ];

                    $currentNode[] = $node;
                    $stack[] = &$currentNode;
                    $currentNode = &$currentNode[array_key_last($currentNode)]['children'];
                    break;

                case 'slot_close':
                    $currentNode = &$stack[array_key_last($stack)];
                    array_pop($stack);
                    break;

                case 'text':
                    if (trim($token['content']) !== '') {
                        $currentNode[] = [
                            'type' => 'text',
                            'content' => $token['content'],
                        ];
                    }
                    break;
            }
        }

        return $ast;
    }

    public function transform(array $ast, callable $callback, bool $postOrder = false): array
    {
        $transformNode = function ($node, $tagLevel = 0) use ($callback, $postOrder, &$transformNode) {
            if (!is_array($node)) return $node;

            // Pre-order traversal: transform parent before children
            if (!$postOrder) {
                $transformed = $callback($node, $tagLevel);
                if ($transformed === null) return null;
                if (!is_array($transformed)) return $transformed;
                $node = $transformed;
            }

            // Transform children
            if (isset($node['children'])) {
                $node['children'] = array_filter(
                    array_map(
                        fn($child) => $transformNode($child, $node['type'] === 'tag' ? $tagLevel + 1 : $tagLevel),
                        $node['children']
                    ),
                    fn($child) => $child !== null
                );
            }

            // Post-order traversal: transform parent after children
            if ($postOrder) {
                $transformed = $callback($node, $tagLevel);
                if ($transformed === null) return null;
                if (!is_array($transformed)) return $transformed;
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
            switch ($node['type']) {
                case 'tag':
                    $prefix = $node['prefix'] ?? $this->prefixes[0];
                    // Strip namespace from name when rendering
                    $name = $node['name'];
                    if (isset($this->prefixes[$prefix]['namespace']) && str_starts_with($name, $this->prefixes[$prefix]['namespace'])) {
                        $name = substr($name, strlen($this->prefixes[$prefix]['namespace']));
                    }
                    $output .= "<{$prefix}{$name}";

                    // Render attributes as a string if present
                    if (!empty($node['attributes'])) {
                        $output .= " {$node['attributes']}";
                    }

                    if (isset($node['self_closing']) && $node['self_closing']) {
                        $output .= " />";
                    } else {
                        $output .= ">";
                        $output .= $this->render($node['children']);
                        $output .= "</{$prefix}{$name}>";
                    }
                    break;

                case 'slot':
                    if (isset($node['slot_style']) && $node['slot_style'] === 'short') {
                        // Use short slot syntax
                        $output .= "<{$this->currentSlotPrefix}:{$node['name']}";

                        // Add attributes if present
                        if (!empty($node['attributes'])) {
                            $output .= " {$node['attributes']}";
                        }

                        $output .= ">";
                        $output .= $this->render($node['children']);
                        $output .= "</{$this->currentSlotPrefix}:{$node['name']}>";
                    } else {
                        // Use standard slot syntax
                        $output .= "<{$this->currentSlotPrefix} name=\"{$node['name']}\"";

                        // Add other attributes if present (excluding the name which we've already added)
                        if (!empty($node['attributes'])) {
                            // Since we're using name="value" syntax explicitly, we need to avoid duplicating name attribute
                            if (preg_match('/^class="([^"]*)"/', $node['attributes'], $matches)) {
                                $output .= " class=\"{$matches[1]}\"";
                            } else if (trim($node['attributes']) !== '') {
                                $output .= " " . $node['attributes'];
                            }
                        }

                        $output .= ">";
                        $output .= $this->render($node['children']);
                        $output .= "</{$this->currentSlotPrefix}>";
                    }
                    break;

                case 'text':
                    $output .= $node['content'];
                    break;
            }
        }

        return $output;
    }

    public function isStaticNode(array $node): bool
    {
        if ($node['type'] !== 'tag') {
            return false;
        }

        // Check if attributes contain dynamic expressions
        if (!empty($node['attributes'])) {
            // Check if attributes string contains : attribute or {{ expressions
            if (preg_match('/(^|\s):[a-zA-Z]/', $node['attributes']) || str_contains($node['attributes'], '{'.'{')) {
                return false;
            }
        }

        // Check that all children are text nodes
        foreach ($node['children'] ?? [] as $child) {
            if ($child['type'] !== 'text') {
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