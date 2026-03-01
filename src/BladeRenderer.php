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
        protected BladeCompiler $blade,
        protected Factory $factory,
        protected BlazeRuntime $runtime,
        protected BlazeManager $manager,
    ) {}

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
    public function render(string $template): string
    {
        $temporaryCachePath = $this->getTemporaryCachePath();

        File::ensureDirectoryExists($temporaryCachePath);

        $restoreFactory = $this->freezeObjectProperties($this->factory, [
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

        $restoreCompiler = $this->freezeObjectProperties($this->blade, [
            'cachePath' => $temporaryCachePath,
            'rawBlocks' => [],
            'footer' => [],
            'prepareStringsForCompilationUsing' => [
                function ($input) {
                    if (Unblaze::hasUnblaze($input)) {
                        $input = Unblaze::processUnblazeDirectives($input);
                    };

                    $input = $this->manager->compileForFolding($input, $this->blade->getPath());

                    return $input;
                },
            ],
            'path' => null,
            'forElseCounter' => 0,
            'firstCaseInSwitch' => true,
            'lastSection' => null,
            'lastFragment' => null,
        ]);

        $restoreRuntime = $this->freezeObjectProperties($this->runtime, [
            'compiled' => [],
            'paths' => [],
            'compiledPath' => $temporaryCachePath,
            'dataStack' => [],
            'slotsStack' => [],
        ]);

        try {
            $this->manager->startFolding();

            $result = $this->blade->render($template, deleteCachedView: true);
        } finally {
            $restoreCompiler();
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

        return function () use ($reflection, $object, $frozen) {
            foreach ($frozen as $name => $value) {
                $property = $reflection->getProperty($name);
                $property->setValue($object, $value);
            }
        };
    }
}
