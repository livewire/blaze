<?php

describe('cache invalidation', function () {
    beforeEach(function () {
        app('blade.compiler')->anonymousComponentNamespace('', 'x');
        app('blade.compiler')->anonymousComponentPath(__DIR__ . '/fixtures/components');
    });

    function compileWithFrontmatter(string $input): string {
        return app('blaze')->collectAndAppendFrontMatter($input, function($template) {
            return app('blaze')->compile($template);
        });
    }

    it('embeds folded component metadata as frontmatter', function () {
        $input = '<x-button>Save</x-button>';
        $output = compileWithFrontmatter($input);

        // Check that frontmatter exists with component metadata
        expect($output)->toContain('<'.'?php # [BlazeFolded]:');
        expect($output)->toContain('{'.__DIR__ . '/fixtures/components/button.blade.php}');
        expect($output)->toContain('{button}');
    });

    it('dispatches ComponentFolded events during compilation', function () {
        \Illuminate\Support\Facades\Event::fake([\Livewire\Blaze\Events\ComponentFolded::class]);

        $input = '<x-button>Save</x-button>';
        app('blaze')->compile($input);

        \Illuminate\Support\Facades\Event::assertDispatched(\Livewire\Blaze\Events\ComponentFolded::class, function($event) {
            return $event->name === 'button'
                && str_contains($event->path, 'button.blade.php')
                && is_int($event->filemtime);
        });
    });

    it('parses frontmatter correctly', function () {
        $frontMatter = new \Livewire\Blaze\FrontMatter();

        // First, let's see what the actual frontmatter looks like
        $input = '<x-button>Save</x-button>';
        $output = compileWithFrontmatter($input);

        // Debug: show what the actual output looks like
        // echo "Actual output: " . $output . "\n";

        $parsed = $frontMatter->parseFromTemplate($output);
        expect($parsed)->toHaveCount(1);
    });

    it('detects expired folded dependencies', function () {
        $frontMatter = new \Livewire\Blaze\FrontMatter();

        // Get the actual generated frontmatter format first
        $input = '<x-button>Save</x-button>';
        $actualOutput = compileWithFrontmatter($input);

        // Now test with current filemtime (not expired)
        expect($frontMatter->sourceContainsExpiredFoldedDependencies($actualOutput))->toBeFalse();

        // Test with old filemtime by manually creating expired data
        $buttonPath = __DIR__ . '/fixtures/components/button.blade.php';
        $currentFilemtime = filemtime($buttonPath);
        $oldFilemtime = $currentFilemtime - 3600; // 1 hour ago

        // Create expired frontmatter manually
        $expiredFrontmatter = "<?php # [BlazeFolded]:{button}:{{$buttonPath}}:{{$oldFilemtime}} ?>\n";
        $expiredOutput = $expiredFrontmatter . "<button type=\"button\">Save</button>";

        // Check parsing
        $parsed = $frontMatter->parseFromTemplate($expiredOutput);
        expect($parsed)->toHaveCount(1);
        expect((int)$parsed[0][3])->toBeLessThan($currentFilemtime); // old filemtime should be less than current

        expect($frontMatter->sourceContainsExpiredFoldedDependencies($expiredOutput))->toBeTrue();

        // Test with non-existent file (expired)
        $missingOutput = str_replace($buttonPath, '/path/that/does/not/exist.blade.php', $actualOutput);
        expect($frontMatter->sourceContainsExpiredFoldedDependencies($missingOutput))->toBeTrue();
    });
});