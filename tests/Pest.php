<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Livewire\Blaze\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

/**
 * Compile a component fixture and return its compiled output.
 */
function compile(string $filename): string
{
    $path = __DIR__ . '/Feature/fixtures/' . $filename;

    app('blade.compiler')->compile($path);

    return File::get(
        app('blade.compiler')->getCompiledPath($path)
    );
}

/**
 * Render a Blade view with optional inline components.
 *
 * Usage:
 *   // With components and data
 *   blade(
 *       components: [
 *           'button' => <<<'BLADE'
 *              @blaze
 *              @props(['label'])
 *              <button>{{ $label }}</button>
 *              BLADE
 *          ,
 *       ],
 *       view: '<x-button :label="$text" />',
 *       data: ['text' => 'Click'],
 *   );
 *
 * Note: Component templates should not reference other inline components.
 * For nested component tests, use fixtures instead.
 */
function blade(string $view, array $components = [], array $data = []): string
{
    $paths = [];
    $processedView = $view;

    foreach ($components as $name => $template) {
        $uniqueName = $name . '-' . substr(md5(uniqid()), 0, 8);
        $path = __DIR__ . "/Feature/fixtures/{$uniqueName}.blade.php";

        File::put($path, $template);
        $paths[] = $path;

        // Replace component name in view (opening and closing tags)
        $processedView = preg_replace(
            '/<x-' . preg_quote($name, '/') . '(?=[>\s\/])/',
            '<x-' . $uniqueName,
            $processedView
        );
        $processedView = str_replace("</x-{$name}>", "</x-{$uniqueName}>", $processedView);
    }

    try {
        return Blade::render($processedView, $data);
    } finally {
        foreach ($paths as $path) {
            File::delete($path);
            $compiledPath = app('blade.compiler')->getCompiledPath($path);
            if (File::exists($compiledPath)) {
                File::delete($compiledPath);
            }
        }
    }
}