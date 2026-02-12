<?php

use Livewire\Blaze\Blaze;
use Livewire\Blaze\Compiler\Compiler;
use Livewire\Blaze\Memoizer\Memoizer;
use Livewire\Blaze\Nodes\ComponentNode;
use Livewire\Blaze\Nodes\TextNode;
use Livewire\Blaze\Support\ComponentSource;

describe('Blaze::optimize() API', function () {
    beforeEach(function () {
        Blaze::optimize()->clear();
        app('blade.compiler')->anonymousComponentPath(__DIR__.'/fixtures/path-config');
    });

    it('can access optimize config via facade', function () {
        $config = Blaze::optimize();

        expect($config)->toBeInstanceOf(\Livewire\Blaze\Config::class);
    });

    it('returns same instance on multiple calls', function () {
        $config1 = Blaze::optimize();
        $config2 = Blaze::optimize();

        expect($config1)->toBe($config2);
    });

    it('compiles component in configured directory without @blaze directive', function () {
        // Register directory — button.blade.php has no @blaze directive
        $source = new ComponentSource('button');

        Blaze::optimize()->in(dirname($source->path));

        $tagCompiler = new Compiler(config: Blaze::optimize());

        $node = new ComponentNode(
            name: 'button',
            prefix: 'x-',
            attributeString: '',
            children: [],
            selfClosing: true,
        );

        // Component in configured directory should compile to a TextNode
        expect($tagCompiler->compile($node))->toBeInstanceOf(TextNode::class);
    });

    it('excludes component in directory with compile: false', function () {
        $source = new ComponentSource('legacy.old');

        // Register parent directory, then exclude subdirectory
        Blaze::optimize()
            ->in(dirname(dirname($source->path)))
            ->in(dirname($source->path), compile: false);

        $tagCompiler = new Compiler(config: Blaze::optimize());

        $node = new ComponentNode(
            name: 'legacy.old',
            prefix: 'x-',
            attributeString: '',
            children: [],
            selfClosing: true,
        );

        // Component in excluded directory should NOT compile (returns original ComponentNode)
        expect($tagCompiler->compile($node))->toBeInstanceOf(ComponentNode::class);
    });

    it('component @blaze directive overrides path compile: false', function () {
        $source = new ComponentSource('legacy2.overridden');

        // Register directory with compile: false
        Blaze::optimize()->in(dirname($source->path), compile: false);

        $tagCompiler = new Compiler(config: Blaze::optimize());

        $node = new ComponentNode(
            name: 'legacy2.overridden',
            prefix: 'x-',
            attributeString: '',
            children: [],
            selfClosing: true,
        );

        // Component has @blaze directive, so it should be compiled even though path says compile: false
        expect($tagCompiler->compile($node))->toBeInstanceOf(TextNode::class);
    });

    it('path-based fold setting acts as default', function () {
        $source = new ComponentSource('button');

        Blaze::optimize()->in(dirname($source->path), fold: true);

        expect(Blaze::optimize()->shouldFold(realpath($source->path)))->toBeTrue();
    });

    it('component @blaze(fold: false) overrides path fold: true', function () {
        $source = new ComponentSource('cards2.card');

        Blaze::optimize()->in(dirname($source->path), fold: true);

        $folder = new \Livewire\Blaze\Folder\Folder(
            config: Blaze::optimize(),
        );

        $node = new ComponentNode(
            name: 'cards2.card',
            prefix: 'x-',
            attributeString: '',
            children: [],
            selfClosing: true,
        );

        // Component explicitly sets fold: false, so it should NOT be folded (returns original ComponentNode)
        expect($folder->fold($node))->toBeInstanceOf(ComponentNode::class);
    });

    it('path-based memo setting acts as default', function () {
        $source = new ComponentSource('icons.icon');

        // Register directory with memo: true
        Blaze::optimize()->in(dirname($source->path), memo: true);

        $tagCompiler = new Compiler(config: Blaze::optimize());

        $memoizer = new Memoizer(
            config: Blaze::optimize(),
        );

        $node = new ComponentNode(
            name: 'icons.icon',
            prefix: 'x-',
            attributeString: '',
            children: [],
            selfClosing: true,
        );

        expect($memoizer->isMemoizable($node))->toBeTrue();
    });
});
