<?php

use Illuminate\View\Engines\FileEngine;
use Illuminate\View\View;
use Livewire\Blaze\BlazeManager;

describe('FileEngine compatibility', function () {
    it('handles views with FileEngine gracefully without throwing errors', function () {
        $blazeManager = app(BlazeManager::class);

        // Create a mock view that uses FileEngine (which doesn't have getCompiler method)
        $fileEngine = new FileEngine(app('files'));

        // Create a temporary PHP file (not a blade file)
        $tempPath = sys_get_temp_dir() . '/test-view-' . uniqid() . '.php';
        file_put_contents($tempPath, '<?php echo "Hello"; ?>');

        try {
            $view = new View(
                app('view'),
                $fileEngine,
                'test',
                $tempPath,
                []
            );

            // This should not throw an error
            $result = $blazeManager->viewContainsExpiredFrontMatter($view);

            // For non-compiler engines, it should return false (no expired frontmatter)
            expect($result)->toBeFalse();
        } finally {
            // Clean up
            @unlink($tempPath);
        }
    });

    it('caches the result for FileEngine views', function () {
        $blazeManager = app(BlazeManager::class);

        $fileEngine = new FileEngine(app('files'));

        $tempPath = sys_get_temp_dir() . '/test-view-cached-' . uniqid() . '.php';
        file_put_contents($tempPath, '<?php echo "Hello"; ?>');

        try {
            $view = new View(
                app('view'),
                $fileEngine,
                'test',
                $tempPath,
                []
            );

            // First call
            $result1 = $blazeManager->viewContainsExpiredFrontMatter($view);

            // Second call (should use cached result)
            $result2 = $blazeManager->viewContainsExpiredFrontMatter($view);

            expect($result1)->toBeFalse();
            expect($result2)->toBeFalse();
            expect($result1)->toBe($result2);
        } finally {
            @unlink($tempPath);
        }
    });

    it('still works correctly with CompilerEngine (blade views)', function () {
        // This ensures our fix doesn't break the normal blade compilation flow
        $blazeManager = app(BlazeManager::class);

        // Set up blade compiler
        app('blade.compiler')->anonymousComponentPath(__DIR__ . '/fixtures/components');

        // Create a blade view
        $template = '<x-button>Click me</x-button>';
        $compiled = $blazeManager->compile($template);

        expect($compiled)->toBeString();
        expect($compiled)->toContain('button');
    });
});
