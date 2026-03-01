<?php

namespace Livewire\Blaze;

use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Facades\File;
use Illuminate\View\Compilers\BladeCompiler;
use Livewire\Blaze\Runtime\BlazeRuntime;
use ReflectionClass;

/**
 * Handles isolated Blade rendering used during compile-time folding.
 */
class BladeRenderer
{
    public function __construct(
        protected BladeCompiler $compiler,
        protected Factory $factory,
        protected BlazeRuntime $runtime,
        protected BlazeManager $manager,
    ) {}

    /**
     * Render a Blade template string in an isolated context.
     */
    public function render(string $template): string
    {
        return $this->isolatedRender($template);
    }

    /**
     * Get the temporary cache directory path used during isolated rendering.
     */
    public function getTemporaryCachePath(): string
    {
        return config('view.compiled').'/blaze';
    }

    /**
     * Render a Blade template string in isolation by freezing and restoring compiler state.
     */
    public function isolatedRender(string $template): string
    {
        $compiler = $this->compiler;

        $temporaryCachePath = $this->getTemporaryCachePath();

        File::ensureDirectoryExists($temporaryCachePath);

        $factory = $this->factory;

        [$factory, $restoreFactory] = $this->freezeObjectProperties($factory, [
            'renderCount' => 0,
            'renderedOnce' => [],
            'sections' => [],
            'sectionStack' => [],
            'pushes' => [],
            'prepends' => [],
            'pushStack' => [],
            'componentStack' => [],
            'componentData' => [],
            'currentComponentData' => [],
            'slots' => [],
            'slotStack' => [],
            'fragments' => [],
            'fragmentStack' => [],
            'loopsStack' => [],
            'translationReplacements' => [],
        ]);

        [$compiler, $restore] = $this->freezeObjectProperties($compiler, [
            'cachePath' => $temporaryCachePath,
            'rawBlocks' => [],
            'footer' => [],
            'prepareStringsForCompilationUsing' => [
                function ($input) use ($compiler) {
                    if (Unblaze::hasUnblaze($input)) {
                        $input = Unblaze::processUnblazeDirectives($input);
                    };

                    $input = $this->manager->compileForFolding($input, $compiler->getPath());

                    return $input;
                },
            ],
            'path' => null,
            'forElseCounter' => 0,
            'firstCaseInSwitch' => true,
            'lastSection' => null,
            'lastFragment' => null,
        ]);

        [$runtime, $restoreRuntime] = $this->freezeObjectProperties($this->runtime, [
            'compiled' => [],
            'paths' => [],
            'compiledPath' => $temporaryCachePath,
            'dataStack' => [],
            'slotsStack' => [],
        ]);

        try {
            $this->manager->startFolding();

            $result = $compiler->render($template, deleteCachedView: true);
        } finally {
            $restore();
            $restoreFactory();
            $restoreRuntime();

            $this->manager->stopFolding();
        }

        $result = Unblaze::replaceUnblazePrecompiledDirectives($result);

        return $result;
    }

    /**
     * Delete the temporary cache directory created during isolated rendering.
     */
    public function deleteTemporaryCacheDirectory(): void
    {
        File::deleteDirectory($this->getTemporaryCachePath());
    }

    /**
     * Snapshot object properties and return a restore closure to revert them.
     */
    protected function freezeObjectProperties(object $object, array $properties)
    {
        $reflection = new ReflectionClass($object);

        $frozen = [];

        foreach ($properties as $key => $value) {
            $name = is_numeric($key) ? $value : $key;

            $property = $reflection->getProperty($name);

            $frozen[$name] = $property->getValue($object);

            if (! is_numeric($key)) {
                $property->setValue($object, $value);
            }
        }

        return [
            $object,
            function () use ($reflection, $object, $frozen) {
                foreach ($frozen as $name => $value) {
                    $property = $reflection->getProperty($name);
                    $property->setValue($object, $value);
                }
            },
        ];
    }
}
