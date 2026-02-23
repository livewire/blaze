<?php

namespace Livewire\Blaze\Compiler;

use Livewire\Blaze\BladeService;
use Livewire\Blaze\Support\Directives;
use Livewire\Blaze\Support\Utils;
use Livewire\Blaze\Blaze;

/**
 * Compiles Blaze component templates into PHP function definitions.
 */
class Wrapper
{
    public function __construct(
        protected PropsCompiler $propsCompiler = new PropsCompiler,
        protected AwareCompiler $awareCompiler = new AwareCompiler,
    ) {}

    /**
     * Compile a component template into a function definition.
     *
     * @param  string  $compiled  The compiled template (after TagCompiler processing)
     * @param  string  $path  The component file path
     * @param  string|null  $source  The original source template (for detecting $slot usage)
     */
    public function wrap(string $compiled, string $path, ?string $source = null): string
    {
        $source ??= $compiled;
        $name = (Blaze::isFolding() ? '__' : '_') . Utils::hash($path);

        $compiled = BladeService::compileDirective($compiled, 'props', function ($expression) {
            return $this->propsCompiler->compile($expression);
        });

        $compiled = BladeService::compileDirective($compiled, 'aware', function ($expression) {
            return $this->awareCompiler->compile($expression);
        });

        $isDebugging = app('blaze')->isDebugging() && ! app('blaze')->isFolding();
        $sourceUsesThis = str_contains($source, '$this');

        $output = '';

        // Start of function definition...

        $output .= '<'.'?php if (!function_exists(\''.$name.'\')):'."\n";
        $output .= 'function '.$name.'($__blaze, $__data = [], $__slots = [], $__bound = [], $__this = null) {'."\n";

        if ($isDebugging) {
            $componentName = app('blaze.runtime')->debugger->extractComponentName($path);
            $output .= '$__blaze->debugger->increment(\''.$name.'\', \''.$componentName.'\');'."\n";
            $output .= '$__blaze->debugger->startTimer(\''.$name.'\');'."\n";
        }

        if ($sourceUsesThis) {
            $output .= '$__blazeFn = function () use ($__blaze, $__data, $__slots, $__bound) {'."\n";
        }

        $output .= 'if (($__data[\'attributes\'] ?? null) instanceof \Illuminate\View\ComponentAttributeBag) { $__data = $__data + $__data[\'attributes\']->all(); unset($__data[\'attributes\']); }'."\n";
        $output .= '$attributes = \\Livewire\\Blaze\\Runtime\\BlazeAttributeBag::sanitized($__data, $__bound);'."\n";
        $output .= 'extract($__slots, EXTR_SKIP); unset($__slots);'."\n";
        $output .= 'extract($__data, EXTR_SKIP); unset($__data, $__bound);'."\n";
        $output .= $this->globalVariables($source, $compiled);
        $output .= '?>' . "\n";

        // Content...
        $output .= $compiled;

        // End of function definition...

        $output .= '<?php ';

        if ($sourceUsesThis) {
            $output .= '}; if ($__this !== null) { $__blazeFn->call($__this); } else { $__blazeFn(); }'."\n";
        }

        if ($isDebugging) {
            $output .= '$__blaze->debugger->stopTimer(\''.$name.'\');'."\n";
        }

        $output .= '} endif; ?>';

        return $output;
    }
    
    protected function globalVariables(string $source, string $compiled): string
    {
        $output = '';

        $output .= '$__env = $__blaze->env;' . "\n";

        if ($this->hasEchoHandlers() && ($this->hasEchoSyntax($source) || $this->hasEchoSyntax($compiled))) {
            $output .= '$__bladeCompiler = app(\'blade.compiler\');' . "\n";
        }

        $output .= implode("\n", array_filter([
            '$app' => '$app = $__blaze->app;',
            '$errors' => '$errors = $__blaze->errors;',
            '@error' => '$errors = $__blaze->errors;',
            '$__livewire' => '$__livewire = $__env->shared(\'__livewire\');',
            '@entangle' => '$__livewire = $__env->shared(\'__livewire\');',
            '$slot' => '$__slots[\'slot\'] ??= new \Illuminate\View\ComponentSlot(\'\');',
        ], function ($pattern) use ($source, $compiled) {
            return str_contains($source, $pattern) || str_contains($compiled, $pattern);
        }, ARRAY_FILTER_USE_KEY)) . "\n";

        return $output;
    }

    /**
     * Check if the Blade compiler has any echo handlers registered.
     */
    protected function hasEchoHandlers(): bool
    {
        $compiler = app('blade.compiler');
        $reflection = new \ReflectionProperty($compiler, 'echoHandlers');

        return ! empty($reflection->getValue($compiler));
    }

    /**
     * Check if the source contains Blade echo syntax.
     */
    protected function hasEchoSyntax(string $source): bool
    {
        return preg_match('/\{\{.+?\}\}|\{!!.+?!!\}/s', $source) === 1;
    }
}
