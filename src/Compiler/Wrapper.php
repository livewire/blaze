<?php

namespace Livewire\Blaze\Compiler;

use Livewire\Blaze\BladeService;
use Livewire\Blaze\Support\Directives;
use Livewire\Blaze\Support\Utils;

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
        $name = '_'.Utils::hash($path);

        $directives = new Directives($source);

        $propsExpression = $directives->get('props');
        $awareExpression = $directives->get('aware');

        $propAssignments = $propsExpression ? $this->propsCompiler->compile($propsExpression) : null;
        $awareAssignments = $awareExpression ? $this->awareCompiler->compile($awareExpression) : null;

        $compiled = $this->strip($compiled, 'blaze');
        $compiled = $this->strip($compiled, 'props');
        $compiled = $this->strip($compiled, 'aware');

        $propsUseAttributes = str_contains($propAssignments, '$attributes');
        $sourceUsesAttributes = str_contains($this->strip($source, 'props'), '$attributes') || str_contains($source, '<flux:delegate-component');
        $needsEchoHandler = $this->hasEchoHandlers() && $this->hasEchoSyntax($source);

        return implode('', array_filter([
            '<'.'?php if (!function_exists(\''.$name.'\')):'."\n",
            'function '.$name.'($__blaze, $__data = [], $__slots = [], $__bound = []) {'."\n",
            app('blaze')->isDebugging() && ! app('blaze')->isFolding() ? '$__blaze->increment(\''.$name.'\');'."\n" : null,
            '$__env = $__blaze->env;'."\n",
            $needsEchoHandler ? '$__bladeCompiler = app(\'blade.compiler\');'."\n" : null,
            'if (($__data[\'attributes\'] ?? null) instanceof \Illuminate\View\ComponentAttributeBag) { $__data = $__data + $__data[\'attributes\']->all(); unset($__data[\'attributes\']); }'."\n",
            str_contains($source, '$app') ? '$app = $__blaze->app;'."\n" : null,
            str_contains($source, '$errors') ? '$errors = $__blaze->errors;'."\n" : null,
            str_contains($source, '$slot') ? '$__slots[\'slot\'] ??= new \Illuminate\View\ComponentSlot(\'\');'."\n" : null,
            'extract($__slots, EXTR_SKIP);'."\n",
            'unset($__slots);'."\n",
            $propsExpression === null ? 'extract($__data, EXTR_SKIP);'."\n" : null,
            $awareAssignments,
            $propsUseAttributes ? '$attributes = new \\Livewire\\Blaze\\Runtime\\BlazeAttributeBag($__data);'."\n" : null,
            $propAssignments,
            $sourceUsesAttributes ? '$attributes = \\Livewire\\Blaze\\Runtime\\BlazeAttributeBag::sanitized($__data, $__bound);'."\n" : null,
            'unset($__data, $__bound); ?>',
            $compiled,
            '<'.'?php } endif; ?>',
        ]));
    }

    /**
     * Strip a directive and its surrounding whitespace from content.
     */
    protected function strip(string $content, string $directive): string
    {
        // Protect raw block placeholders so restoreRawContent doesn't resolve them
        $content = preg_replace('/@__raw_block_(\d+)__@/', '__BLAZE_RAW_BLOCK_$1__', $content);

        $marker = '__BLAZE_STRIP__';

        $content = BladeService::compileDirective($content, $directive, function () use ($marker) {
            return $marker;
        });

        $content = preg_replace('/^[ \t]*' . preg_quote($marker, '/') . '\s*/m', '', $content);

        $content = preg_replace('/__BLAZE_RAW_BLOCK_(\d+)__/', '@__raw_block_$1__@', $content);

        return $content;
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
