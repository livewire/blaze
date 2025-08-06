<?php

namespace Livewire\Blaze\Parser;

use Livewire\Blaze\Parser\Nodes\Node;
use Livewire\Blaze\Parser\Nodes\TagNode;
use Livewire\Blaze\Parser\Nodes\TextNode;
use Livewire\Blaze\Parser\Nodes\SlotNode;
use Livewire\Blaze\Parser\Tokens\Token;
use Livewire\Blaze\Parser\Tokens\TagOpenToken;
use Livewire\Blaze\Parser\Tokens\TagSelfCloseToken;
use Livewire\Blaze\Parser\Tokens\TagCloseToken;
use Livewire\Blaze\Parser\Tokens\SlotOpenToken;
use Livewire\Blaze\Parser\Tokens\SlotCloseToken;
use Livewire\Blaze\Parser\Tokens\TextToken;

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

    // Tokenizer state
    protected string $content = '';
    protected int $position = 0;
    protected int $length = 0;
    protected array $tokens = [];
    protected string $buffer = '';
    protected ?Token $currentToken = null;
    protected array $tagStack = [];
    protected string $currentPrefix = '';
    protected string $currentSlotPrefix = '';

    public function tokenize(string $content): array
    {
        $this->resetTokenizer($content);

        $state = State::TEXT;

        while (!$this->isAtEnd()) {
            $state = match($state) {
                State::TEXT => $this->handleTextState(),
                State::TAG_OPEN => $this->handleTagOpenState(),
                State::TAG_CLOSE => $this->handleTagCloseState(),
                State::ATTRIBUTE_NAME => $this->handleAttributeState(),
                State::SLOT => $this->handleSlotState(),
                State::SLOT_CLOSE => $this->handleSlotCloseState(),
                State::SHORT_SLOT => $this->handleShortSlotState(),
                default => throw new \RuntimeException("Unknown state: $state"),
            };
        }

        $this->flushBuffer();

        return $this->tokens;
    }

    public function parse(array $tokens): array
    {
        $stack = new ParseStack();

        foreach ($tokens as $token) {
            match(get_class($token)) {
                TagOpenToken::class => $this->handleTagOpen($token, $stack),
                TagSelfCloseToken::class => $this->handleTagSelfClose($token, $stack),
                TagCloseToken::class => $this->handleTagClose($token, $stack),
                SlotOpenToken::class => $this->handleSlotOpen($token, $stack),
                SlotCloseToken::class => $this->handleSlotClose($token, $stack),
                TextToken::class => $this->handleText($token, $stack),
                default => throw new \RuntimeException('Unknown token type: ' . get_class($token))
            };
        }

        return $stack->getAst();
    }

    public function render(array $ast): string
    {
        return implode('', array_map([$this, 'renderNode'], $ast));
    }

    public function transform(array $ast, callable $callback, bool $postOrder = false): array
    {
        return array_filter(
            array_map(fn($node) => $this->transformNode($node, 0, $callback, $postOrder)),
            fn($node) => $node !== null
        );
    }

    public function isStaticNode(Node $node): bool
    {
        if (!($node instanceof TagNode)) {
            return false;
        }

        // Check for dynamic expressions in attributes
        if (!empty($node->attributes)) {
            if (preg_match('/(^|\s):[a-zA-Z]/', $node->attributes) ||
                str_contains($node->attributes, '{'.'{')) {
                return false;
            }
        }

        // All children must be text nodes
        foreach ($node->children as $child) {
            if (!($child instanceof TextNode)) {
                return false;
            }
        }

        return true;
    }

    // =================================================================
    // Tokenizer Methods
    // =================================================================

    protected function resetTokenizer(string $content): void
    {
        $this->content = $content;
        $this->position = 0;
        $this->length = strlen($content);
        $this->tokens = [];
        $this->buffer = '';
        $this->currentToken = null;
        $this->tagStack = [];
        $this->currentPrefix = '';
        $this->currentSlotPrefix = '';
    }

    protected function handleTextState(): State
    {
        $char = $this->current();

        // Check for slot tags first
        if ($char === '<') {
            // Try slot open tags
            if ($slotInfo = $this->matchSlotOpen()) {
                $this->flushBuffer();
                $this->currentSlotPrefix = $slotInfo['prefix'];

                if ($slotInfo['isShort']) {
                    $this->currentToken = new SlotOpenToken(slotStyle: 'short');
                    $this->advance(strlen('<' . $slotInfo['prefix'] . ':'));
                    return State::SHORT_SLOT;
                } else {
                    $this->currentToken = new SlotOpenToken(slotStyle: 'standard');
                    $this->advance(strlen('<' . $slotInfo['prefix']));
                    return State::SLOT;
                }
            }

            // Try slot close tags
            if ($slotInfo = $this->matchSlotClose()) {
                $this->flushBuffer();
                $this->currentToken = new SlotCloseToken();
                $this->currentSlotPrefix = $slotInfo['prefix'];
                $this->advance(strlen('</' . $slotInfo['prefix']));
                return State::SLOT_CLOSE;
            }

            // Try component open tags
            if ($prefixInfo = $this->matchComponentOpen()) {
                $this->flushBuffer();
                $this->currentPrefix = $prefixInfo['prefix'];
                $this->currentToken = new TagOpenToken(
                    name: '',
                    prefix: $prefixInfo['prefix'],
                    namespace: $prefixInfo['namespace']
                );
                $this->advance(strlen('<' . $prefixInfo['prefix']));
                return State::TAG_OPEN;
            }

            // Try component close tags
            if ($this->peek(1) === '/' && ($prefixInfo = $this->matchComponentClose())) {
                $this->flushBuffer();
                $this->currentPrefix = $prefixInfo['prefix'];
                $this->currentToken = new TagCloseToken(
                    name: '',
                    prefix: $prefixInfo['prefix'],
                    namespace: $prefixInfo['namespace']
                );
                $this->advance(strlen('</' . $prefixInfo['prefix']));
                return State::TAG_CLOSE;
            }
        }

        // Regular text
        $this->buffer .= $char;
        $this->advance();
        return State::TEXT;
    }

    protected function handleTagOpenState(): State
    {
        if ($name = $this->matchTagName()) {
            $this->currentToken->name = $name;
            $this->tagStack[] = $name;
            $this->advance(strlen($name));
            return State::ATTRIBUTE_NAME;
        }

        $this->advance();
        return State::TAG_OPEN;
    }

    protected function handleTagCloseState(): State
    {
        if ($name = $this->matchTagName()) {
            $this->currentToken->name = $name;
            array_pop($this->tagStack);
            $this->advance(strlen($name));

            if ($this->current() === '>') {
                $this->tokens[] = $this->currentToken;
                $this->advance();
                return State::TEXT;
            }
        }

        $this->advance();
        return State::TAG_CLOSE;
    }

    protected function handleAttributeState(): State
    {
        $char = $this->current();

        // Skip whitespace
        if ($char === ' ') {
            $this->advance();
            return State::ATTRIBUTE_NAME;
        }

        // End of tag
        if ($char === '>') {
            $this->tokens[] = $this->currentToken;
            $this->advance();
            return State::TEXT;
        }

        // Self-closing tag
        if ($char === '/' && $this->peek() === '>') {
            $this->currentToken = new TagSelfCloseToken(
                name: $this->currentToken->name,
                prefix: $this->currentToken->prefix,
                namespace: $this->currentToken->namespace,
                attributes: $this->currentToken->attributes
            );
            array_pop($this->tagStack);
            $this->tokens[] = $this->currentToken;
            $this->advance(2);
            return State::TEXT;
        }

        // Collect attributes
        $attributes = $this->collectAttributes();
        if ($attributes !== null) {
            $this->currentToken->attributes = $attributes;
        }

        return State::ATTRIBUTE_NAME;
    }

    protected function handleSlotState(): State
    {
        $char = $this->current();

        if ($char === ' ') {
            $this->advance();
            return State::SLOT;
        }

        // Check for name attribute
        if ($this->match('/^name="([^"]+)"/')) {
            $matches = [];
            preg_match('/^name="([^"]+)"/', $this->remaining(), $matches);
            $this->currentToken->name = $matches[1];
            $this->advance(strlen($matches[0]));
            return State::ATTRIBUTE_NAME;
        }

        if ($char === '>') {
            $this->tokens[] = $this->currentToken;
            $this->advance();
            return State::TEXT;
        }

        $this->advance();
        return State::SLOT;
    }

    protected function handleSlotCloseState(): State
    {
        // Check for :name pattern
        if ($this->match('/^:[a-zA-Z0-9-]+/')) {
            $matches = [];
            preg_match('/^:[a-zA-Z0-9-]+/', $this->remaining(), $matches);
            $this->currentToken->name = substr($matches[0], 1);
            $this->advance(strlen($matches[0]));
        }

        if ($this->current() === '>') {
            $this->tokens[] = $this->currentToken;
            $this->advance();
            return State::TEXT;
        }

        $this->advance();
        return State::SLOT_CLOSE;
    }

    protected function handleShortSlotState(): State
    {
        if ($name = $this->matchSlotName()) {
            $this->currentToken->name = $name;
            $this->advance(strlen($name));

            // Collect attributes until >
            $attrBuffer = '';
            while (!$this->isAtEnd() && $this->current() !== '>') {
                $attrBuffer .= $this->current();
                $this->advance();
            }

            if (trim($attrBuffer) !== '') {
                $this->currentToken->attributes = trim($attrBuffer);
            }

            if ($this->current() === '>') {
                $this->tokens[] = $this->currentToken;
                $this->advance();
                return State::TEXT;
            }
        }

        $this->advance();
        return State::SHORT_SLOT;
    }

    protected function collectAttributes(): ?string
    {
        $attrString = '';
        $inSingleQuote = false;
        $inDoubleQuote = false;
        $braceCount = 0;
        $bracketCount = 0;
        $parenCount = 0;

        while (!$this->isAtEnd()) {
            $char = $this->current();
            $prevChar = $this->position > 0 ? $this->content[$this->position - 1] : '';

            // Track quote state
            if ($char === '"' && !$inSingleQuote && $prevChar !== '\\') {
                $inDoubleQuote = !$inDoubleQuote;
            } elseif ($char === "'" && !$inDoubleQuote && $prevChar !== '\\') {
                $inSingleQuote = !$inSingleQuote;
            }

            // Track nesting only outside quotes
            if (!$inSingleQuote && !$inDoubleQuote) {
                match($char) {
                    '{' => $braceCount++,
                    '}' => $braceCount--,
                    '[' => $bracketCount++,
                    ']' => $bracketCount--,
                    '(' => $parenCount++,
                    ')' => $parenCount--,
                    default => null
                };
            }

            // Check for end of attributes
            if (($char === '>' || ($char === '/' && $this->peek() === '>')) &&
                !$inSingleQuote && !$inDoubleQuote &&
                $braceCount === 0 && $bracketCount === 0 && $parenCount === 0) {
                break;
            }

            $attrString .= $char;
            $this->advance();
        }

        return trim($attrString) !== '' ? trim($attrString) : null;
    }

    // Pattern matching helpers
    protected function matchSlotOpen(): ?array
    {
        foreach ($this->prefixes as $prefix => $config) {
            $slotPrefix = $config['slot'];

            // Check for short slot syntax
            if ($this->matchesAt('<' . $slotPrefix . ':')) {
                return ['prefix' => $slotPrefix, 'isShort' => true];
            }

            // Check for standard slot syntax
            if ($this->matchesAt('<' . $slotPrefix)) {
                $nextChar = $this->peek(strlen('<' . $slotPrefix));
                if ($nextChar !== ':') {
                    return ['prefix' => $slotPrefix, 'isShort' => false];
                }
            }
        }
        return null;
    }

    protected function matchSlotClose(): ?array
    {
        foreach ($this->prefixes as $prefix => $config) {
            $slotPrefix = $config['slot'];
            if ($this->matchesAt('</' . $slotPrefix)) {
                return ['prefix' => $slotPrefix];
            }
        }
        return null;
    }

    protected function matchComponentOpen(): ?array
    {
        foreach ($this->prefixes as $prefix => $config) {
            if ($this->matchesAt('<' . $prefix)) {
                return [
                    'prefix' => $prefix,
                    'namespace' => $config['namespace'] ?? ''
                ];
            }
        }
        return null;
    }

    protected function matchComponentClose(): ?array
    {
        foreach ($this->prefixes as $prefix => $config) {
            if ($this->matchesAt('</' . $prefix)) {
                return [
                    'prefix' => $prefix,
                    'namespace' => $config['namespace'] ?? ''
                ];
            }
        }
        return null;
    }

    protected function matchTagName(): ?string
    {
        if (preg_match('/^[a-zA-Z0-9-\.:]+/', $this->remaining(), $matches)) {
            return $matches[0];
        }
        return null;
    }

    protected function matchSlotName(): ?string
    {
        if (preg_match('/^[a-zA-Z0-9-]+/', $this->remaining(), $matches)) {
            return $matches[0];
        }
        return null;
    }

    protected function match(string $pattern): bool
    {
        return preg_match($pattern, $this->remaining()) === 1;
    }

    protected function matchesAt(string $string): bool
    {
        return substr($this->content, $this->position, strlen($string)) === $string;
    }

    // Character navigation
    protected function current(): string
    {
        return $this->isAtEnd() ? '' : $this->content[$this->position];
    }

    protected function peek(int $offset = 1): string
    {
        $pos = $this->position + $offset;
        return $pos >= $this->length ? '' : $this->content[$pos];
    }

    protected function remaining(): string
    {
        return substr($this->content, $this->position);
    }

    protected function advance(int $count = 1): void
    {
        $this->position += $count;
    }

    protected function isAtEnd(): bool
    {
        return $this->position >= $this->length;
    }

    protected function flushBuffer(): void
    {
        if ($this->buffer !== '') {
            $this->tokens[] = new TextToken($this->buffer);
            $this->buffer = '';
        }
    }

    // =================================================================
    // Parser Methods
    // =================================================================

    protected function handleTagOpen(TagOpenToken $token, ParseStack $stack): void
    {
        $node = new TagNode(
            name: $token->namespace . $token->name,
            prefix: $token->prefix,
            attributes: $token->attributes,
            children: [],
            selfClosing: false
        );

        $stack->pushContainer($node);
    }

    protected function handleTagSelfClose(TagSelfCloseToken $token, ParseStack $stack): void
    {
        $node = new TagNode(
            name: $token->namespace . $token->name,
            prefix: $token->prefix,
            attributes: $token->attributes,
            children: [],
            selfClosing: true
        );

        $stack->addToRoot($node);
    }

    protected function handleTagClose(TagCloseToken $token, ParseStack $stack): void
    {
        $stack->popContainer();
    }

    protected function handleSlotOpen(SlotOpenToken $token, ParseStack $stack): void
    {
        $node = new SlotNode(
            name: $token->name ?? '',
            attributes: $token->attributes,
            slotStyle: $token->slotStyle,
            children: []
        );

        $stack->pushContainer($node);
    }

    protected function handleSlotClose(SlotCloseToken $token, ParseStack $stack): void
    {
        $stack->popContainer();
    }

    protected function handleText(TextToken $token, ParseStack $stack): void
    {
        // Always preserve text content, including whitespace
        $node = new TextNode(content: $token->content);
        $stack->addToRoot($node);
    }

    // =================================================================
    // Renderer Methods
    // =================================================================

    protected function renderNode(Node $node): string
    {
        return match(get_class($node)) {
            TagNode::class => $this->renderTag($node),
            SlotNode::class => $this->renderSlot($node),
            TextNode::class => $node->content,
            default => throw new \RuntimeException('Unknown node type: ' . get_class($node))
        };
    }

    protected function renderTag(TagNode $node): string
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
        if ($node->slotStyle === 'short') {
            return $this->renderShortSlot($node);
        }

        return $this->renderStandardSlot($node);
    }

    protected function buildOpeningTag(TagNode $node): string
    {
        $prefix = $node->prefix;
        $name = $this->stripNamespaceFromName($node->name, $prefix);

        $output = "<{$prefix}{$name}";

        if (!empty($node->attributes)) {
            $output .= " {$node->attributes}";
        }

        return $output;
    }

    protected function buildClosingTag(TagNode $node): string
    {
        $prefix = $node->prefix;
        $name = $this->stripNamespaceFromName($node->name, $prefix);

        return "</{$prefix}{$name}>";
    }

    protected function renderShortSlot(SlotNode $node): string
    {
        $output = "<{$this->currentSlotPrefix}:{$node->name}";

        if (!empty($node->attributes)) {
            $output .= " {$node->attributes}";
        }

        $output .= ">";
        $output .= $this->render($node->children);
        $output .= "</{$this->currentSlotPrefix}:{$node->name}>";

        return $output;
    }

    protected function renderStandardSlot(SlotNode $node): string
    {
        $output = "<{$this->currentSlotPrefix} name=\"{$node->name}\"";

        if (!empty($node->attributes)) {
            // Handle attributes carefully to avoid duplicating name
            if (preg_match('/^class="([^"]*)"/', $node->attributes, $matches)) {
                $output .= " class=\"{$matches[1]}\"";
            } elseif (trim($node->attributes) !== '') {
                $output .= " {$node->attributes}";
            }
        }

        $output .= ">";
        $output .= $this->render($node->children);
        $output .= "</{$this->currentSlotPrefix}>";

        return $output;
    }

    protected function stripNamespaceFromName(string $name, string $prefix): string
    {
        $namespace = $this->prefixes[$prefix]['namespace'] ?? '';

        if ($namespace && str_starts_with($name, $namespace)) {
            return substr($name, strlen($namespace));
        }

        return $name;
    }

    // =================================================================
    // Transform Methods
    // =================================================================

    protected function transformNode(Node $node, int $tagLevel, callable $callback, bool $postOrder): ?Node
    {
        // Pre-order transformation
        if (!$postOrder) {
            $transformed = $callback($node, $tagLevel);
            if ($transformed === null || !($transformed instanceof Node)) {
                return $transformed;
            }
            $node = $transformed;
        }

        // Transform children for container nodes
        if (($node instanceof TagNode || $node instanceof SlotNode) && !empty($node->children)) {
            $node->children = array_filter(
                array_map(
                    fn($child) => $this->transformNode(
                        $child,
                        $node instanceof TagNode ? $tagLevel + 1 : $tagLevel,
                        $callback,
                        $postOrder
                    ),
                    $node->children
                ),
                fn($child) => $child !== null
            );
        }

        // Post-order transformation
        if ($postOrder) {
            $transformed = $callback($node, $tagLevel);
            if ($transformed === null || !($transformed instanceof Node)) {
                return $transformed;
            }
            $node = $transformed;
        }

        return $node;
    }
}