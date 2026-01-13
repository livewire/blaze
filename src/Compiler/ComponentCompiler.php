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
     */
    public function compile(string $template, string $path): string
    {
        $name = '_' . TagCompiler::hash($path);

        $propsExpression = $this->directiveMatcher->extractExpression($template, 'props');
        $awareExpression = $this->directiveMatcher->extractExpression($template, 'aware');

        $propAssignments = $propsExpression ? $this->propsCompiler->compile($propsExpression) : null;
        $awareAssignments = $awareExpression ? $this->awareCompiler->compile($awareExpression) : null;

        $template = $this->directiveMatcher->strip($template, 'blaze');
        $template = $this->directiveMatcher->strip($template, 'props');
        $template = $this->directiveMatcher->strip($template, 'aware');

        return implode('', array_filter([
            '<' . '?php if (!function_exists(\'' . $name . '\')):' . "\n",
            'function ' . $name . '($__blaze, $__data = [], $__slots = [], $__bound = []) {' . "\n",
            '$__env = $__blaze->env;' . "\n",
            str_contains($template, '$app') ? '$app = $__blaze->app;' . "\n" : null,
            str_contains($template, '$errors') ? '$errors = $__blaze->errors;' . "\n" : null,
            str_contains($template, '$slot') ? '$__slots[\'slot\'] ??= new \Illuminate\View\ComponentSlot(\'\');' . "\n" : null,
            'extract($__slots, EXTR_SKIP);' . "\n",
            'unset($__slots);' . "\n",
            $propsExpression === null ? 'extract($__data, EXTR_SKIP);' . "\n" : null,
            $awareAssignments,
            str_contains($propAssignments, '$attributes') ? '$attributes = new \\Livewire\\Blaze\\Runtime\\BlazeAttributeBag($__data);' . "\n" : null,
            $propAssignments,
            str_contains($template, '$attributes') ? '$attributes = \\Livewire\\Blaze\\Runtime\\BlazeAttributeBag::sanitized($__data, $__bound);' . "\n" : null,
            'unset($__data, $__bound); ?>',
            $template,
            '<' . '?php } endif; ?>',
        ]));
    }
}
