<?php

namespace Livewire\Blaze;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Blade;

class BladeHacker
{
    public function render(string $template): string
    {
        return Blade::render($template);
    }

    public function containsLaravelExceptionView(string $input): bool
    {
        return str_contains($input, 'laravel-exceptions');
    }

    public function earliestPreCompilationHook(callable $callback)
    {
        app()->booted(function () use ($callback) {
            app('blade.compiler')->prepareStringsForCompilationUsing(function ($input) use ($callback) {
                $compiler = app('blade.compiler');

                $reflection = new \ReflectionClass($compiler);
                $storeVerbatimBlocks = $reflection->getMethod('storeVerbatimBlocks');
                $storeVerbatimBlocks->setAccessible(true);

                $output = $storeVerbatimBlocks->invoke($compiler, $input);

                $output = $callback($input);

                return $output;
            });
        });
    }

    public function viewCacheInvalidationHook(callable $callback)
    {
        Event::listen('composing:*', function ($event, $params) use ($callback) {
            $view = $params[0];

            if (! $view instanceof \Illuminate\View\View) {
                return;
            }

            $invalidate = fn () => $view->getEngine()->getCompiler()->compile($view->getPath());

            $callback($view, $invalidate);
        });
    }

    public function componentNameToPath($name): string
    {
        // Ingest a component name. For example:
        // Blade components: <x-form.input> would be $name = 'form.input'
        // Namespaced components: <x-pages::dashboard> would be $name = 'pages::dashboard'

        // Then identify the source file path of that component and return it.

        // Laravel has this logic built-in, but we need to access it properly
        // The simplest approach is to leverage the anonymous component paths directly

        $compiler = app('blade.compiler');
        $viewFinder = app('view.finder');

        // Get anonymous component paths (includes both namespaced and regular)
        $reflection = new \ReflectionClass($compiler);
        $pathsProperty = $reflection->getProperty('anonymousComponentPaths');
        $pathsProperty->setAccessible(true);
        $paths = $pathsProperty->getValue($compiler) ?? [];

        // Handle namespaced components
        if (str_contains($name, '::')) {
            [$namespace, $componentName] = explode('::', $name, 2);
            $componentPath = str_replace('.', '/', $componentName);

            // Look for namespaced anonymous component
            foreach ($paths as $pathData) {
                if (isset($pathData['prefix']) && $pathData['prefix'] === $namespace) {
                    $basePath = rtrim($pathData['path'], '/');

                    // Try direct component file first (e.g., pages::auth.login -> auth/login.blade.php)
                    $fullPath = $basePath . '/' . $componentPath . '.blade.php';
                    if (file_exists($fullPath)) {
                        return $fullPath;
                    }

                    // For root components, try index.blade.php (e.g., pages::auth -> auth/index.blade.php)
                    if (!str_contains($componentPath, '/')) {
                        $indexPath = $basePath . '/' . $componentPath . '/index.blade.php';
                        if (file_exists($indexPath)) {
                            return $indexPath;
                        }

                        // Try same-name file (e.g., pages::auth -> auth/auth.blade.php)
                        $sameNamePath = $basePath . '/' . $componentPath . '/' . $componentPath . '.blade.php';
                        if (file_exists($sameNamePath)) {
                            return $sameNamePath;
                        }
                    }
                }
            }

            // Fallback to regular namespaced view lookup
            try {
                return $viewFinder->find(str_replace('::', '::components.', $name));
            } catch (\Exception $e) {
                return '';
            }
        }

        // For regular anonymous components, check the registered paths
        $componentPath = str_replace('.', '/', $name);

        // Check each registered anonymous component path (without prefix)
        foreach ($paths as $pathData) {
            // Only check paths without a prefix for regular anonymous components
            if (!isset($pathData['prefix']) || $pathData['prefix'] === null) {
                $registeredPath = $pathData['path'] ?? $pathData;

                if (is_string($registeredPath)) {
                    $basePath = rtrim($registeredPath, '/');

                    // Try direct component file first (e.g., form.input -> form/input.blade.php)
                    $fullPath = $basePath . '/' . $componentPath . '.blade.php';
                    if (file_exists($fullPath)) {
                        return $fullPath;
                    }

                    // For root components, try index.blade.php (e.g., form -> form/index.blade.php)
                    if (!str_contains($componentPath, '/')) {
                        $indexPath = $basePath . '/' . $componentPath . '/index.blade.php';
                        if (file_exists($indexPath)) {
                            return $indexPath;
                        }

                        // Try same-name file (e.g., card -> card/card.blade.php)
                        $sameNamePath = $basePath . '/' . $componentPath . '/' . $componentPath . '.blade.php';
                        if (file_exists($sameNamePath)) {
                            return $sameNamePath;
                        }
                    }
                }
            }
        }

        // Fallback to standard components namespace
        try {
            return $viewFinder->find("components.{$name}");
        } catch (\Exception $e) {
            return '';
        }
    }
}
