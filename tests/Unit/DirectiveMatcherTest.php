<?php

use Livewire\Blaze\Compiler\DirectiveMatcher;

describe('match', function () {
    it('matches simple directive without parentheses', function () {
        $matcher = new DirectiveMatcher();
        $template = '@blaze some content';

        $matches = $matcher->match($template, 'blaze');

        expect($matches)->toHaveCount(1);
        expect($matches[0]['match'])->toStartWith('@blaze');
        expect($matches[0]['expression'])->toBeNull();
    });

    it('matches directive with simple expression', function () {
        $matcher = new DirectiveMatcher();
        $template = '@props(["type" => "button"])';

        $matches = $matcher->match($template, 'props');

        expect($matches)->toHaveCount(1);
        expect($matches[0]['match'])->toBe('@props(["type" => "button"])');
        expect($matches[0]['expression'])->toBe('["type" => "button"]');
    });

    it('matches directive with nested parentheses', function () {
        $matcher = new DirectiveMatcher();
        $template = '@props(["type" => foo()])';

        $matches = $matcher->match($template, 'props');

        expect($matches)->toHaveCount(1);
        expect($matches[0]['match'])->toBe('@props(["type" => foo()])');
        expect($matches[0]['expression'])->toBe('["type" => foo()]');
    });

    it('matches directive with deeply nested parentheses', function () {
        $matcher = new DirectiveMatcher();
        $template = '@props(["variant" => $attributes->get("data-variant", "primary")])';

        $matches = $matcher->match($template, 'props');

        expect($matches)->toHaveCount(1);
        expect($matches[0]['match'])->toBe('@props(["variant" => $attributes->get("data-variant", "primary")])');
        expect($matches[0]['expression'])->toBe('["variant" => $attributes->get("data-variant", "primary")]');
    });

    it('matches directive with closure containing parentheses', function () {
        $matcher = new DirectiveMatcher();
        $template = '@props(["callback" => fn() => "value"])';

        $matches = $matcher->match($template, 'props');

        expect($matches)->toHaveCount(1);
        expect($matches[0]['match'])->toBe('@props(["callback" => fn() => "value"])');
        expect($matches[0]['expression'])->toBe('["callback" => fn() => "value"]');
    });

    it('handles parentheses inside strings correctly', function () {
        $matcher = new DirectiveMatcher();
        $template = '@props(["label" => "Click (here)"])';

        $matches = $matcher->match($template, 'props');

        expect($matches)->toHaveCount(1);
        expect($matches[0]['match'])->toBe('@props(["label" => "Click (here)"])');
        expect($matches[0]['expression'])->toBe('["label" => "Click (here)"]');
    });

    it('matches multiple directives', function () {
        $matcher = new DirectiveMatcher();
        $template = '@props(["a"]) some content @props(["b"])';

        $matches = $matcher->match($template, 'props');

        expect($matches)->toHaveCount(2);
        expect($matches[0]['expression'])->toBe('["a"]');
        expect($matches[1]['expression'])->toBe('["b"]');
    });

    it('returns empty array when directive not found', function () {
        $matcher = new DirectiveMatcher();
        $template = 'no directive here';

        $matches = $matcher->match($template, 'props');

        expect($matches)->toBeEmpty();
    });

    it('matches directive after @ symbol', function () {
        $matcher = new DirectiveMatcher();

        $matches = $matcher->match('@@props(["a"])', 'props');

        expect($matches)->toHaveCount(1);
        expect($matches[0]['match'])->toBe('@props(["a"])');
    });
});

describe('extractExpression', function () {
    it('extracts expression from first directive match', function () {
        $matcher = new DirectiveMatcher();
        $template = '@props(["type" => "button"])';

        $expression = $matcher->extractExpression($template, 'props');

        expect($expression)->toBe('["type" => "button"]');
    });

    it('trims whitespace from expression', function () {
        $matcher = new DirectiveMatcher();
        $template = '@props(  ["type" => "button"]  )';

        $expression = $matcher->extractExpression($template, 'props');

        expect($expression)->toBe('["type" => "button"]');
    });

    it('returns null when directive not found', function () {
        $matcher = new DirectiveMatcher();
        $template = 'no directive here';

        $expression = $matcher->extractExpression($template, 'props');

        expect($expression)->toBeNull();
    });

    it('returns null for directive without parentheses', function () {
        $matcher = new DirectiveMatcher();
        $template = '@blaze some content';

        $expression = $matcher->extractExpression($template, 'blaze');

        expect($expression)->toBeNull();
    });
});

describe('has', function () {
    it('returns true when directive exists', function () {
        $matcher = new DirectiveMatcher();
        $template = '@props(["type" => "button"])';

        expect($matcher->has($template, 'props'))->toBeTrue();
    });

    it('returns true for directive without parentheses', function () {
        $matcher = new DirectiveMatcher();
        $template = '@blaze';

        expect($matcher->has($template, 'blaze'))->toBeTrue();
    });

    it('returns false when directive does not exist', function () {
        $matcher = new DirectiveMatcher();
        $template = 'no directive here';

        expect($matcher->has($template, 'props'))->toBeFalse();
    });
});

describe('replace', function () {
    it('replaces directive with callback result', function () {
        $matcher = new DirectiveMatcher();
        $template = '@props(["type" => "button"])';

        $result = $matcher->replace($template, 'props', function ($match, $expression) {
            return "REPLACED: {$expression}";
        });

        expect($result)->toBe('REPLACED: ["type" => "button"]');
    });

    it('replaces multiple directives', function () {
        $matcher = new DirectiveMatcher();
        $template = '@props(["a"]) content @props(["b"])';

        $result = $matcher->replace($template, 'props', function ($match, $expression) {
            return "[{$expression}]";
        });

        expect($result)->toBe('[["a"]] content [["b"]]');
    });

    it('preserves content when directive not found', function () {
        $matcher = new DirectiveMatcher();
        $template = 'no directive here';

        $result = $matcher->replace($template, 'props', fn () => 'REPLACED');

        expect($result)->toBe('no directive here');
    });
});

describe('strip', function () {
    it('removes directive and trailing whitespace', function () {
        $matcher = new DirectiveMatcher();
        $template = '@props(["type" => "button"]) content';

        $result = $matcher->strip($template, 'props');

        expect($result)->toBe('content');
    });

    it('removes directive without parentheses', function () {
        $matcher = new DirectiveMatcher();
        $template = '@blaze content';

        $result = $matcher->strip($template, 'blaze');

        expect($result)->toBe('content');
    });

    it('removes directive and trailing newline', function () {
        $matcher = new DirectiveMatcher();
        $template = "@props([\"type\" => \"button\"])\ncontent";

        $result = $matcher->strip($template, 'props');

        expect($result)->toBe('content');
    });

    it('removes directive and multiple trailing newlines', function () {
        $matcher = new DirectiveMatcher();
        $template = "@blaze\n\ncontent";

        $result = $matcher->strip($template, 'blaze');

        expect($result)->toBe('content');
    });

    it('removes directive with trailing whitespace and newline', function () {
        $matcher = new DirectiveMatcher();
        $template = "@blaze   \ncontent";

        $result = $matcher->strip($template, 'blaze');

        expect($result)->toBe('content');
    });

    it('handles directive at end of template', function () {
        $matcher = new DirectiveMatcher();
        $template = 'content @blaze';

        $result = $matcher->strip($template, 'blaze');

        expect($result)->toBe('content ');
    });
});

describe('edge cases', function () {
    it('handles multiline expressions', function () {
        $matcher = new DirectiveMatcher();
        $template = "@props([
    'type' => 'button',
    'disabled' => false,
])";

        $matches = $matcher->match($template, 'props');

        expect($matches)->toHaveCount(1);
        expect($matches[0]['expression'])->toContain("'type' => 'button'");
        expect($matches[0]['expression'])->toContain("'disabled' => false");
    });

    it('handles directive with whitespace before parentheses', function () {
        $matcher = new DirectiveMatcher();
        $template = '@props  (["type" => "button"])';

        $matches = $matcher->match($template, 'props');

        expect($matches)->toHaveCount(1);
        expect($matches[0]['match'])->toBe('@props  (["type" => "button"])');
    });

    it('handles empty expression', function () {
        $matcher = new DirectiveMatcher();
        $template = '@props([])';

        $matches = $matcher->match($template, 'props');

        expect($matches)->toHaveCount(1);
        expect($matches[0]['expression'])->toBe('[]');
    });

    it('handles complex chained method calls', function () {
        $matcher = new DirectiveMatcher();
        $template = '@props(["type" => $attributes->whereStartsWith("type")->first()])';

        $matches = $matcher->match($template, 'props');

        expect($matches)->toHaveCount(1);
        expect($matches[0]['expression'])->toBe('["type" => $attributes->whereStartsWith("type")->first()]');
    });
});
