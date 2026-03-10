<?php

namespace Livewire\Blaze;

use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Component;
use Illuminate\View\ComponentSlot;
use Livewire\Blaze\Parser\Attribute;
use Livewire\Blaze\Parser\Nodes\ComponentNode;
use Livewire\Blaze\Parser\Nodes\SlotNode;
use Livewire\Blaze\Runtime\BlazeRuntime;
use Livewire\Blaze\Support\ComponentSource;
use Livewire\Blaze\Support\Utils;
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
    public function render(ComponentNode $component, ComponentSource $source): string
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
            'compiledPath' => $temporaryCachePath,
            'dataStack' => [],
            'slotsStack' => [],
        ]);

        $obLevel = ob_get_level();
        $hash = Utils::hash($source->path);
        $path = $temporaryCachePath . '/' . $hash . '.php';
        $fn = '__' . $hash;

        $this->manager->startFolding();

        try {
            if (! file_exists($path)) {
                $this->blade->compile($source->path);
            }

            $attributes = Arr::mapWithKeys($component->attributes, function (Attribute $attribute) {
                return [$attribute->name => $attribute->getStaticValue()];
            });
            
            $slots = Arr::mapWithKeys($component->children, function (SlotNode $slot) {
                return [$slot->name => new ComponentSlot($slot->content())];
            });

            $this->runtime->pushData($attributes);
            $this->runtime->pushSlots($slots);

            ob_start();

            require_once $path;

            $fn(
                __blaze: $this->runtime,
                __data: $attributes,
                __slots: $slots,
            );

            $result = ltrim(ob_get_clean());
        } finally {
            while (ob_get_level() > $obLevel) {
                ob_end_clean();
            }

            $this->runtime->popData();
            $this->manager->stopFolding();

            $restoreCompiler();
            $restoreFactory();
            $restoreRuntime();
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
    protected function freezeObjectProperties(object|string $object, array $properties)
    {
        $reflection = new ReflectionClass($object);

        $frozen = [];

        foreach ($properties as $key => $value) {
            $name = is_numeric($key) ? $value : $key;

            $property = $reflection->getProperty($name);

            $frozen[$name] = $property->getValue(is_object($object) ? $object : null);

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
