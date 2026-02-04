<?php

use Livewire\Blaze\Blaze;
use Livewire\Blaze\Support\ComponentSource;

describe('Blaze::optimize() API', function () {
    beforeEach(function () {
        Blaze::optimize()->clear();
    });

    it('can access optimize config via facade', function () {
        $config = Blaze::optimize();

        expect($config)->toBeInstanceOf(\Livewire\Blaze\BlazeConfig::class);
    });

    it('returns same instance on multiple calls', function () {
        $config1 = Blaze::optimize();
        $config2 = Blaze::optimize();

        expect($config1)->toBe($config2);
    });

    it('compiles component in configured directory without @blaze directive', function () {
        $componentPath = __DIR__.'/fixtures/path-config/button.blade.php';
        $componentDir = dirname($componentPath);

        // Register directory
        Blaze::optimize()->in($componentDir);

        // Create test component without @blaze directive
        if (! is_dir($componentDir)) {
            mkdir($componentDir, 0755, true);
        }
        file_put_contents($componentPath, '<button {{ $attributes }}>{{ $slot }}</button>');

        try {
            // Verify the component is recognized as Blaze component
            $tagCompiler = new \Livewire\Blaze\Compiler\TagCompiler(
                fn () => $componentPath,
                Blaze::optimize()
            );

            $reflection = new ReflectionMethod($tagCompiler, 'isBlazeComponent');
            $reflection->setAccessible(true);

            expect($reflection->invoke($tagCompiler, $componentPath))->toBeTrue();
        } finally {
            // Cleanup
            @unlink($componentPath);
            @rmdir($componentDir);
        }
    });

    it('excludes component in directory with compile: false', function () {
        $componentPath = __DIR__.'/fixtures/path-config/legacy/old.blade.php';
        $componentDir = dirname($componentPath);
        $parentDir = dirname($componentDir);

        // Register directories
        Blaze::optimize()
            ->in($parentDir)
            ->in($componentDir, compile: false);

        // Create test component without @blaze directive
        if (! is_dir($componentDir)) {
            mkdir($componentDir, 0755, true);
        }
        file_put_contents($componentPath, '<button>{{ $slot }}</button>');

        try {
            $tagCompiler = new \Livewire\Blaze\Compiler\TagCompiler(
                fn () => $componentPath,
                Blaze::optimize()
            );

            $reflection = new ReflectionMethod($tagCompiler, 'isBlazeComponent');
            $reflection->setAccessible(true);

            expect($reflection->invoke($tagCompiler, $componentPath))->toBeFalse();
        } finally {
            // Cleanup
            @unlink($componentPath);
            @rmdir($componentDir);
            @rmdir($parentDir);
        }
    });

    it('component @blaze directive overrides path compile: false', function () {
        $componentPath = __DIR__.'/fixtures/path-config/legacy2/overridden.blade.php';
        $componentDir = dirname($componentPath);

        // Register directory with compile: false
        Blaze::optimize()->in($componentDir, compile: false);

        // Create test component WITH @blaze directive
        if (! is_dir($componentDir)) {
            mkdir($componentDir, 0755, true);
        }
        file_put_contents($componentPath, "@blaze\n<button>{{ \$slot }}</button>");

        try {
            $tagCompiler = new \Livewire\Blaze\Compiler\TagCompiler(
                fn () => $componentPath,
                Blaze::optimize()
            );

            $reflection = new ReflectionMethod($tagCompiler, 'isBlazeComponent');
            $reflection->setAccessible(true);

            // Component has @blaze directive, so it should be compiled even though path says compile: false
            expect($reflection->invoke($tagCompiler, $componentPath))->toBeTrue();
        } finally {
            // Cleanup
            @unlink($componentPath);
            @rmdir($componentDir);
        }
    });

    it('path-based fold setting acts as default', function () {
        $componentPath = __DIR__.'/fixtures/path-config/cards/card.blade.php';
        $componentDir = dirname($componentPath);

        // Register directory with fold: true
        Blaze::optimize()->in($componentDir, fold: true);

        // Create test component without fold parameter in @blaze
        if (! is_dir($componentDir)) {
            mkdir($componentDir, 0755, true);
        }
        file_put_contents($componentPath, '<button>Static</button>');

        try {
            expect(Blaze::optimize()->shouldFold(realpath($componentPath)))->toBeTrue();
        } finally {
            // Cleanup
            @unlink($componentPath);
            @rmdir($componentDir);
        }
    });

    it('component @blaze(fold: false) overrides path fold: true', function () {
        $componentPath = __DIR__.'/fixtures/path-config/cards2/card.blade.php';
        $componentDir = dirname($componentPath);

        // Register directory with fold: true
        Blaze::optimize()->in($componentDir, fold: true);

        // Create test component WITH explicit fold: false
        if (! is_dir($componentDir)) {
            mkdir($componentDir, 0755, true);
        }
        file_put_contents($componentPath, "@blaze(fold: false)\n<button>Static</button>");

        try {
            app('blade.compiler')->anonymousComponentPath(__DIR__.'/fixtures/path-config');
            $source = new ComponentSource('cards2.card');

            $folder = new \Livewire\Blaze\Folder\Folder(
                renderBlade: fn ($blade) => $blade,
                renderNodes: fn ($nodes) => '',
                componentNameToPath: fn () => $componentPath,
                config: Blaze::optimize(),
            );

            $reflection = new ReflectionMethod($folder, 'shouldFold');
            $reflection->setAccessible(true);

            // Component explicitly sets fold: false, overriding path default
            expect($reflection->invoke($folder, $source))->toBeFalse();
        } finally {
            // Cleanup
            @unlink($componentPath);
            @rmdir($componentDir);
        }
    });

    it('path-based memo setting acts as default', function () {
        $componentPath = __DIR__.'/fixtures/path-config/icons/icon.blade.php';
        $componentDir = dirname($componentPath);

        // Register directory with memo: true
        Blaze::optimize()->in($componentDir, memo: true);

        // Create test component without memo parameter in @blaze
        if (! is_dir($componentDir)) {
            mkdir($componentDir, 0755, true);
        }
        file_put_contents($componentPath, '<svg></svg>');

        try {
            $tagCompiler = new \Livewire\Blaze\Compiler\TagCompiler(
                fn () => $componentPath,
                Blaze::optimize()
            );

            $memoizer = new \Livewire\Blaze\Memoizer\Memoizer(
                componentNameToPath: fn () => $componentPath,
                compileNode: fn ($node) => $tagCompiler->compile($node)->render(),
                config: Blaze::optimize()
            );

            $node = new \Livewire\Blaze\Nodes\ComponentNode(
                name: 'test',
                prefix: 'x-',
                attributeString: '',
                children: [],
                selfClosing: true
            );

            expect($memoizer->isMemoizable($node))->toBeTrue();
        } finally {
            // Cleanup
            @unlink($componentPath);
            @rmdir($componentDir);
        }
    });
});
