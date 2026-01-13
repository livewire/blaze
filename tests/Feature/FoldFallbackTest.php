<?php

use Livewire\Blaze\Compiler\TagCompiler;
use Livewire\Blaze\Exceptions\PlaceholderNotFoundException;

beforeEach(function () {
    app('blade.compiler')->anonymousComponentPath(__DIR__ . '/fixtures');
});

describe('fold fallback to function compilation', function () {
    it('folds static content successfully', function () {
        $compiled = app('blaze')->compile('<x-fold-fallback.fold-only />');

        // Should be folded (rendered at compile time)
        expect($compiled)->toContain('Static content that should fold');
        expect($compiled)->not->toContain('<x-fold-fallback.fold-only');
        expect($compiled)->not->toContain('$__blaze->ensureCompiled');
    });

    it('renders folded content via wrapper at runtime', function () {
        $result = \Illuminate\Support\Facades\Blade::render('<x-fold-fallback.wrapper />');

        expect($result)->toContain('<div class="wrapper">');
        expect($result)->toContain('Static content that should fold');
    });

    it('falls back to function compilation when folding fails', function () {
        // This component has @blaze(fold: true) but uses json_decode which will fail
        // on the placeholder string during folding, triggering fallback

        $componentPath = __DIR__ . '/fixtures/fold-fallback/will-fail-fold.blade.php';
        $hash = TagCompiler::hash($componentPath);

        $compiled = app('blaze')->compile('<x-fold-fallback.will-fail-fold :value="$jsonString" />');

        // Should NOT contain the raw component tag (that would mean no optimization)
        expect($compiled)->not->toContain('<x-fold-fallback.will-fail-fold');

        // Should contain function compilation output (ensureCompiled + function call)
        expect($compiled)->toContain('$__blaze->ensureCompiled');
        expect($compiled)->toContain("_$hash");
        expect($compiled)->toContain('require_once');
    });

    it('still folds when fold succeeds with static value', function () {
        // Same component but with a static JSON string that can be parsed at compile time
        $compiled = app('blaze')->compile('<x-fold-fallback.will-fail-fold value=\'{"key":"hello"}\' />');

        // Should be folded (rendered at compile time)
        expect($compiled)->toContain('Computed: hello');
        expect($compiled)->not->toContain('$__blaze->ensureCompiled');
    });
});

describe('placeholder not found fallback', function () {
    it('falls back to function compilation when attribute placeholder not found in output', function () {
        // This component has @blaze(fold: true) but doesn't use the passed attribute
        // The placeholder won't be found in the rendered output, triggering fallback

        $componentPath = __DIR__ . '/fixtures/fold-fallback/ignores-attribute.blade.php';
        $hash = TagCompiler::hash($componentPath);

        $compiled = app('blaze')->compile('<x-fold-fallback.ignores-attribute :label="$dynamicLabel" />');

        // Should NOT contain the raw component tag (that would mean no optimization)
        expect($compiled)->not->toContain('<x-fold-fallback.ignores-attribute');

        // Should contain function compilation output (ensureCompiled + function call)
        expect($compiled)->toContain('$__blaze->ensureCompiled');
        expect($compiled)->toContain("_$hash");
        expect($compiled)->toContain('require_once');
    });

    it('folds successfully when static attribute not used in output', function () {
        // Static attributes don't get placeholders, so they can be safely ignored
        $compiled = app('blaze')->compile('<x-fold-fallback.ignores-attribute label="static-value" />');

        // Should be folded (static attributes don't need placeholders)
        expect($compiled)->toContain('Static content only');
        expect($compiled)->not->toContain('$__blaze->ensureCompiled');
    });

    it('throws PlaceholderNotFoundException in debug mode when placeholder not found', function () {
        app('blaze')->debug();

        expect(function () {
            app('blaze')->compile('<x-fold-fallback.ignores-attribute :label="$dynamicLabel" />');
        })->toThrow(PlaceholderNotFoundException::class);
    });

    it('includes placeholder name in exception message', function () {
        app('blaze')->debug();

        try {
            app('blaze')->compile('<x-fold-fallback.ignores-attribute :label="$dynamicLabel" />');
            $this->fail('Expected PlaceholderNotFoundException');
        } catch (PlaceholderNotFoundException $e) {
            expect($e->getMessage())->toContain('ATTR_PLACEHOLDER_');
            expect($e->getPlaceholder())->toMatch('/ATTR_PLACEHOLDER_\d+/');
            expect($e->getRenderedSnippet())->toContain('Static content only');
        }
    });
});
