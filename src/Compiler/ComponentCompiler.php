<?php

namespace Livewire\Blaze\Compiler;

/**
 * Compiles Blaze component templates into PHP function definitions.
 */
class ComponentCompiler
{
    public function __construct(
        protected PropsCompiler $propsCompiler = new PropsCompiler,
        protected AwareCompiler $awareCompiler = new AwareCompiler,
        protected DirectiveMatcher $directiveMatcher = new DirectiveMatcher,
    ) {}

    /**
     * Compile a component template into a function definition.
     *
     * @param string $compiled The compiled template (after TagCompiler processing)
     * @param string $path The component file path
     * @param string|null $source The original source template (for detecting $slot usage)
     */
    public function compile(string $compiled, string $path, ?string $source = null): string
    {
        $source ??= $compiled;
        $name = '_' . TagCompiler::hash($path);

        $propsExpression = $this->directiveMatcher->extractExpression($source, 'props');
        $awareExpression = $this->directiveMatcher->extractExpression($source, 'aware');

        $propAssignments = $propsExpression ? $this->propsCompiler->compile($propsExpression) : null;
        $awareAssignments = $awareExpression ? $this->awareCompiler->compile($awareExpression) : null;

        $compiled = $this->directiveMatcher->strip($compiled, 'blaze');
        $compiled = $this->directiveMatcher->strip($compiled, 'props');
        $compiled = $this->directiveMatcher->strip($compiled, 'aware');

        $propsUseAttributes = str_contains($propAssignments, '$attributes');
        $sourceUsesAttributes = str_contains($this->directiveMatcher->strip($source, 'props'), '$attributes') || str_contains($source, '<flux:delegate-component');

        return implode('', array_filter([
            '<' . '?php if (!function_exists(\'' . $name . '\')):' . "\n",
            'function ' . $name . '($__blaze, $__data = [], $__slots = [], $__bound = []) {' . "\n",
            '$__env = $__blaze->env;' . "\n",
            'if (($__data[\'attributes\'] ?? null) instanceof \Illuminate\View\ComponentAttributeBag) { $__data = $__data + $__data[\'attributes\']->all(); unset($__data[\'attributes\']); }' . "\n",
            str_contains($source, '$app') ? '$app = $__blaze->app;' . "\n" : null,
            str_contains($source, '$errors') ? '$errors = $__blaze->errors;' . "\n" : null,
            str_contains($source, '$slot') ? '$__slots[\'slot\'] ??= new \Illuminate\View\ComponentSlot(\'\');' . "\n" : null,
            'extract($__slots, EXTR_SKIP);' . "\n",
            'unset($__slots);' . "\n",
            $propsExpression === null ? 'extract($__data, EXTR_SKIP);' . "\n" : null,
            $awareAssignments,
            $propsUseAttributes ? '$attributes = new \\Livewire\\Blaze\\Runtime\\BlazeAttributeBag($__data);' . "\n" : null,
            $propAssignments,
            $sourceUsesAttributes ? '$attributes = \\Livewire\\Blaze\\Runtime\\BlazeAttributeBag::sanitized($__data, $__bound);' . "\n" : null,
            'unset($__data, $__bound); ?>',
            $compiled,
            '<' . '?php } endif; ?>',
        ]));
    }
}
