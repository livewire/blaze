<?php

use Livewire\Blaze\Folder\AttributeBagAnalyzer;
use Livewire\Blaze\Folder\AttributeBagAnalysisResult;

describe('AttributeBagAnalyzer direct forwarding', function () {
    it('handles $attributes directly', function () {
        $analyzer = new AttributeBagAnalyzer();

        $result = $analyzer->analyze('$attributes');

        expect($result)->toBeInstanceOf(AttributeBagAnalysisResult::class);
        expect($result->included)->toBeNull();
        expect($result->excluded)->toBe([]);
        expect($result->renamed)->toBe([]);
    });

    it('handles $attributes with whitespace', function () {
        $analyzer = new AttributeBagAnalyzer();

        $result = $analyzer->analyze('  $attributes  ');

        expect($result)->toBeInstanceOf(AttributeBagAnalysisResult::class);
        expect($result->included)->toBeNull();
    });
});

describe('AttributeBagAnalyzer merge method', function () {
    it('handles merge with static values only', function () {
        $analyzer = new AttributeBagAnalyzer();

        $result = $analyzer->analyze('$attributes->merge(["class" => "foo"])');

        expect($result)->toBeInstanceOf(AttributeBagAnalysisResult::class);
        expect($result->included)->toBeNull();
        expect($result->excluded)->toBe([]);
        expect($result->renamed)->toBe([]);
    });

    it('tracks single renamed prop in merge', function () {
        $analyzer = new AttributeBagAnalyzer();

        $result = $analyzer->analyze('$attributes->merge(["class" => $variant])');

        expect($result)->toBeInstanceOf(AttributeBagAnalysisResult::class);
        expect($result->renamed)->toBe(['variant' => 'class']);
    });

    it('tracks multiple renamed props in merge', function () {
        $analyzer = new AttributeBagAnalyzer();

        $result = $analyzer->analyze('$attributes->merge(["class" => $variant, "id" => $elementId])');

        expect($result)->toBeInstanceOf(AttributeBagAnalysisResult::class);
        expect($result->renamed)->toBe(['variant' => 'class', 'elementId' => 'id']);
    });

    it('handles merge with mixed static and dynamic values', function () {
        $analyzer = new AttributeBagAnalyzer();

        $result = $analyzer->analyze('$attributes->merge(["class" => "static", "id" => $myId])');

        expect($result)->toBeInstanceOf(AttributeBagAnalysisResult::class);
        // Only the dynamic value is tracked as a renaming
        expect($result->renamed)->toBe(['myId' => 'id']);
    });

    it('handles merge with empty array', function () {
        $analyzer = new AttributeBagAnalyzer();

        $result = $analyzer->analyze('$attributes->merge([])');

        expect($result)->toBeInstanceOf(AttributeBagAnalysisResult::class);
        expect($result->renamed)->toBe([]);
    });
});

describe('AttributeBagAnalyzer only method', function () {
    it('handles only with array of strings', function () {
        $analyzer = new AttributeBagAnalyzer();

        $result = $analyzer->analyze('$attributes->only(["class", "id"])');

        expect($result)->toBeInstanceOf(AttributeBagAnalysisResult::class);
        expect($result->included)->toBe(['class', 'id']);
        expect($result->excluded)->toBe([]);
    });

    it('handles only with single string', function () {
        $analyzer = new AttributeBagAnalyzer();

        $result = $analyzer->analyze('$attributes->only("class")');

        expect($result)->toBeInstanceOf(AttributeBagAnalysisResult::class);
        expect($result->included)->toBe(['class']);
    });

    it('returns null for only with variable argument', function () {
        $analyzer = new AttributeBagAnalyzer();

        $result = $analyzer->analyze('$attributes->only($keys)');

        expect($result)->toBeNull();
    });

    it('returns null for only with function call argument', function () {
        $analyzer = new AttributeBagAnalyzer();

        $result = $analyzer->analyze('$attributes->only(getKeys())');

        expect($result)->toBeNull();
    });
});

describe('AttributeBagAnalyzer except method', function () {
    it('handles except with array of strings', function () {
        $analyzer = new AttributeBagAnalyzer();

        $result = $analyzer->analyze('$attributes->except(["class", "id"])');

        expect($result)->toBeInstanceOf(AttributeBagAnalysisResult::class);
        expect($result->included)->toBeNull();
        expect($result->excluded)->toBe(['class', 'id']);
    });

    it('handles except with single string', function () {
        $analyzer = new AttributeBagAnalyzer();

        $result = $analyzer->analyze('$attributes->except("class")');

        expect($result)->toBeInstanceOf(AttributeBagAnalysisResult::class);
        expect($result->excluded)->toBe(['class']);
    });

    it('returns null for except with variable argument', function () {
        $analyzer = new AttributeBagAnalyzer();

        $result = $analyzer->analyze('$attributes->except($keys)');

        expect($result)->toBeNull();
    });
});

describe('AttributeBagAnalyzer class and style methods', function () {
    it('handles class method', function () {
        $analyzer = new AttributeBagAnalyzer();

        $result = $analyzer->analyze('$attributes->class("foo bar")');

        expect($result)->toBeInstanceOf(AttributeBagAnalysisResult::class);
        expect($result->included)->toBeNull();
        expect($result->excluded)->toBe([]);
        expect($result->renamed)->toBe([]);
    });

    it('handles style method', function () {
        $analyzer = new AttributeBagAnalyzer();

        $result = $analyzer->analyze('$attributes->style("color: red")');

        expect($result)->toBeInstanceOf(AttributeBagAnalysisResult::class);
        expect($result->included)->toBeNull();
    });

    it('handles class with array argument', function () {
        $analyzer = new AttributeBagAnalyzer();

        $result = $analyzer->analyze('$attributes->class(["foo" => true, "bar" => $condition])');

        expect($result)->toBeInstanceOf(AttributeBagAnalysisResult::class);
    });
});

describe('AttributeBagAnalyzer chained methods', function () {
    it('handles except then merge', function () {
        $analyzer = new AttributeBagAnalyzer();

        $result = $analyzer->analyze('$attributes->except(["id"])->merge(["class" => $variant])');

        expect($result)->toBeInstanceOf(AttributeBagAnalysisResult::class);
        expect($result->excluded)->toBe(['id']);
        expect($result->renamed)->toBe(['variant' => 'class']);
    });

    it('handles only then merge', function () {
        $analyzer = new AttributeBagAnalyzer();

        $result = $analyzer->analyze('$attributes->only(["class", "id"])->merge(["data-foo" => $foo])');

        expect($result)->toBeInstanceOf(AttributeBagAnalysisResult::class);
        expect($result->included)->toBe(['class', 'id']);
        expect($result->renamed)->toBe(['foo' => 'data-foo']);
    });

    it('handles merge then except', function () {
        $analyzer = new AttributeBagAnalyzer();

        $result = $analyzer->analyze('$attributes->merge(["class" => $variant])->except(["id"])');

        expect($result)->toBeInstanceOf(AttributeBagAnalysisResult::class);
        expect($result->renamed)->toBe(['variant' => 'class']);
        expect($result->excluded)->toBe(['id']);
    });

    it('handles triple chain', function () {
        $analyzer = new AttributeBagAnalyzer();

        $result = $analyzer->analyze('$attributes->except(["wire:model"])->class("btn")->merge(["type" => $type])');

        expect($result)->toBeInstanceOf(AttributeBagAnalysisResult::class);
        expect($result->excluded)->toBe(['wire:model']);
        expect($result->renamed)->toBe(['type' => 'type']);
    });

    it('handles only then except (intersection)', function () {
        $analyzer = new AttributeBagAnalyzer();

        $result = $analyzer->analyze('$attributes->only(["class", "id", "name"])->except(["id"])');

        expect($result)->toBeInstanceOf(AttributeBagAnalysisResult::class);
        expect($result->included)->toBe(['class', 'name']);
        expect($result->excluded)->toBe(['id']);
    });
});

describe('AttributeBagAnalyzer unsupported methods', function () {
    it('returns null for filter method', function () {
        $analyzer = new AttributeBagAnalyzer();

        $result = $analyzer->analyze('$attributes->filter(fn ($v) => $v)');

        expect($result)->toBeNull();
    });

    it('returns null for whereStartsWith method', function () {
        $analyzer = new AttributeBagAnalyzer();

        $result = $analyzer->analyze('$attributes->whereStartsWith("data-")');

        expect($result)->toBeNull();
    });

    it('returns null for whereDoesntStartWith method', function () {
        $analyzer = new AttributeBagAnalyzer();

        $result = $analyzer->analyze('$attributes->whereDoesntStartWith("wire:")');

        expect($result)->toBeNull();
    });

    it('returns null for all method', function () {
        $analyzer = new AttributeBagAnalyzer();

        $result = $analyzer->analyze('$attributes->all()');

        expect($result)->toBeNull();
    });

    it('returns null for get method', function () {
        $analyzer = new AttributeBagAnalyzer();

        $result = $analyzer->analyze('$attributes->get("class")');

        expect($result)->toBeNull();
    });

    it('returns null for first method', function () {
        $analyzer = new AttributeBagAnalyzer();

        $result = $analyzer->analyze('$attributes->first()');

        expect($result)->toBeNull();
    });

    it('returns null for has method', function () {
        $analyzer = new AttributeBagAnalyzer();

        $result = $analyzer->analyze('$attributes->has("class")');

        expect($result)->toBeNull();
    });

    it('returns null for unknown method', function () {
        $analyzer = new AttributeBagAnalyzer();

        $result = $analyzer->analyze('$attributes->customMethod()');

        expect($result)->toBeNull();
    });

    it('returns null when chain includes unsupported method', function () {
        $analyzer = new AttributeBagAnalyzer();

        $result = $analyzer->analyze('$attributes->merge(["class" => "foo"])->filter(fn ($v) => $v)');

        expect($result)->toBeNull();
    });
});

describe('AttributeBagAnalyzer edge cases', function () {
    it('returns null for non-attributes variable', function () {
        $analyzer = new AttributeBagAnalyzer();

        $result = $analyzer->analyze('$other->merge([])');

        expect($result)->toBeNull();
    });

    it('returns null for invalid expression', function () {
        $analyzer = new AttributeBagAnalyzer();

        $result = $analyzer->analyze('not valid php {{');

        expect($result)->toBeNull();
    });

    it('returns null for empty expression', function () {
        $analyzer = new AttributeBagAnalyzer();

        $result = $analyzer->analyze('');

        expect($result)->toBeNull();
    });
});

describe('AttributeBagAnalysisResult::resolveForwarding', function () {
    it('forwards all props when no filtering', function () {
        $result = new AttributeBagAnalysisResult(
            included: null,
            excluded: [],
            renamed: [],
        );

        $forwarded = $result->resolveForwarding(['foo', 'bar', 'baz']);

        expect($forwarded)->toBe([
            'foo' => 'foo',
            'bar' => 'bar',
            'baz' => 'baz',
        ]);
    });

    it('respects only filtering', function () {
        $result = new AttributeBagAnalysisResult(
            included: ['foo', 'bar'],
            excluded: [],
            renamed: [],
        );

        $forwarded = $result->resolveForwarding(['foo', 'bar', 'baz']);

        expect($forwarded)->toBe([
            'foo' => 'foo',
            'bar' => 'bar',
        ]);
    });

    it('respects except filtering', function () {
        $result = new AttributeBagAnalysisResult(
            included: null,
            excluded: ['bar'],
            renamed: [],
        );

        $forwarded = $result->resolveForwarding(['foo', 'bar', 'baz']);

        expect($forwarded)->toBe([
            'foo' => 'foo',
            'baz' => 'baz',
        ]);
    });

    it('applies renamings', function () {
        $result = new AttributeBagAnalysisResult(
            included: null,
            excluded: [],
            renamed: ['variant' => 'class'],
        );

        $forwarded = $result->resolveForwarding(['foo']);

        expect($forwarded)->toBe([
            'foo' => 'foo',
            'variant' => 'class',
        ]);
    });

    it('combines only filtering and renaming', function () {
        $result = new AttributeBagAnalysisResult(
            included: ['foo'],
            excluded: [],
            renamed: ['variant' => 'class'],
        );

        $forwarded = $result->resolveForwarding(['foo', 'bar']);

        expect($forwarded)->toBe([
            'foo' => 'foo',
            'variant' => 'class',
        ]);
    });

    it('combines except filtering and renaming', function () {
        $result = new AttributeBagAnalysisResult(
            included: null,
            excluded: ['bar'],
            renamed: ['variant' => 'class'],
        );

        $forwarded = $result->resolveForwarding(['foo', 'bar']);

        expect($forwarded)->toBe([
            'foo' => 'foo',
            'variant' => 'class',
        ]);
    });

    it('handles empty parent props with renaming', function () {
        $result = new AttributeBagAnalysisResult(
            included: null,
            excluded: [],
            renamed: ['variant' => 'class'],
        );

        $forwarded = $result->resolveForwarding([]);

        expect($forwarded)->toBe([
            'variant' => 'class',
        ]);
    });
});
