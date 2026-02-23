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

        $needsEchoHandler = $this->hasEchoHandlers() && $this->hasEchoSyntax($source);

        $isDebugging = app('blaze')->isDebugging() && ! app('blaze')->isFolding();
        $componentName = $isDebugging ? app('blaze.runtime')->debugger->extractComponentName($path) : null;
        $sourceUsesThis = str_contains($source, '$this');

        $variables = [
            '$app' => '$app = $__blaze->app;',
            '$errors' => '$errors = $__blaze->errors;',
            '@error' => '$errors = $__blaze->errors;',
            '$__livewire' => '$__livewire = $__env->shared(\'__livewire\');',
            '@entangle' => '$__livewire = $__env->shared(\'__livewire\');',
            '$slot' => '$__slots[\'slot\'] ??= new \Illuminate\View\ComponentSlot(\'\');',
        ];

        $variables = array_filter($variables, fn ($pattern) => str_contains($source, $pattern) || str_contains($compiled, $pattern), ARRAY_FILTER_USE_KEY);
        $variables = implode("\n", $variables);

        return implode('', array_filter([
            '<'.'?php if (!function_exists(\''.$name.'\')):'."\n",
            'function '.$name.'($__blaze, $__data = [], $__slots = [], $__bound = [], $__this = null) {'."\n",
            $sourceUsesThis ? '$__blazeFn = function () use ($__blaze, $__data, $__slots, $__bound) {'."\n" : null,
            $isDebugging ? '$__blaze->debugger->increment(\''.$name.'\', \''.$componentName.'\');'."\n" : null,
            $isDebugging ? '$__blaze->debugger->startTimer(\''.$name.'\');'."\n" : null,
            '$__env = $__blaze->env;'."\n",
            $variables,
            $needsEchoHandler ? '$__bladeCompiler = app(\'blade.compiler\');'."\n" : null,
            'if (($__data[\'attributes\'] ?? null) instanceof \Illuminate\View\ComponentAttributeBag) { $__data = $__data + $__data[\'attributes\']->all(); unset($__data[\'attributes\']); }'."\n",
            'extract($__slots, EXTR_SKIP); unset($__slots);'."\n",
            '$attributes = \\Livewire\\Blaze\\Runtime\\BlazeAttributeBag::sanitized($__data, $__bound);'."\n",
            'extract($__data, EXTR_SKIP);'."\n",
            'unset($__data, $__bound); ?>'."\n",
            $compiled,
            $isDebugging ? '<'.'?php $__blaze->debugger->stopTimer(\''.$name.'\'); ?>' : null,
            $sourceUsesThis ? '<'.'?php };'."\n".'if ($__this !== null) { $__blazeFn->call($__this); } else { $__blazeFn(); }'."\n".'} endif; ?>' : null,
            !$sourceUsesThis ? '<'.'?php } endif; ?>' : null,
        ]));
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
