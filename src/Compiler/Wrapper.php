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

        $directives = new Directives($source);

        $propsExpression = $directives->get('props');
        $awareExpression = $directives->get('aware');

        $propAssignments = $propsExpression ? $this->propsCompiler->compile($propsExpression) : null;
        $awareAssignments = $awareExpression ? $this->awareCompiler->compile($awareExpression) : null;

        $compiled = $this->stripDirective($compiled, 'blaze');
        $compiled = $this->stripDirective($compiled, 'aware');
        $compiled = $this->stripDirective($compiled, 'use');

        $propsUseAttributes = $propAssignments !== null && str_contains($propAssignments, '$attributes');
        $sourceUsesAttributes = str_contains($this->stripDirective($source, 'props'), '$attributes') || str_contains($source, '<flux:delegate-component');

        // Create an early unsanitized $attributes bag only when @props defaults
        // reference $attributes (e.g. $attributes->get()). The sanitized bag is
        // created in-place after @props runs, so template body code gets that one.
        // Pre-@props @php blocks that use $attributes also need this early bag —
        // $sourceUsesAttributes covers that because those blocks are part of the source.
        $needsEarlyAttributes = $propsExpression !== null && ($sourceUsesAttributes || $propsUseAttributes);

        // Compile @props in-place to preserve source execution order.
        // This ensures @php blocks before @props run first, matching Blade's
        // top-to-bottom behavior (e.g. Flux's $attributes->pluck() pattern).
        if ($propsExpression !== null) {
            $inPlaceCode = '';

            // When an early $attributes bag exists, @php blocks before @props
            // may have called $attributes->pluck() which removes keys from
            // the bag but not from $__data.  Since we rebuild a fresh
            // $attributes from $__data after @props, we must sync $__data
            // first so plucked keys stay removed (matching native Blade where
            // there is only one $attributes instance).
            if ($needsEarlyAttributes) {
                $inPlaceCode .= '$__data = array_intersect_key($__data, $attributes->getAttributes());'."\n";
            }

            $inPlaceCode .= $propAssignments ?: '';

            if ($sourceUsesAttributes) {
                $inPlaceCode .= '$attributes = \\Livewire\\Blaze\\Runtime\\BlazeAttributeBag::sanitized($__data, $__bound);'."\n";
            }

            $inPlaceCode .= 'unset($__data, $__bound);'."\n";

            $compiled = $this->replaceDirectiveWithPhp($compiled, 'props', $inPlaceCode);
        } else {
            $compiled = $this->stripDirective($compiled, 'props');
        }

        // Hoist PHP use statements out of the template so they appear before
        // the function definition. We extract from the source because @php
        // blocks are stored as raw block placeholders during compilation and
        // are not yet present in $compiled at this point.
        [$compiled, $useStatements] = $this->extractUseStatements($source, $compiled);

        $needsEchoHandler = $this->hasEchoHandlers() && $this->hasEchoSyntax($source);

        $sourceUsesThis = str_contains($source, '$this');

        $variables = [
            '$app' => '$app = $__blaze->app;',
            '$errors' => '$errors = $__blaze->errors;',
            '@error' => '$errors = $__blaze->errors;',
            '$__livewire' => '$__livewire = $__env->shared(\'__livewire\');',
            '@entangle' => '$__livewire = $__env->shared(\'__livewire\');',
        ];

        $variables = array_filter($variables, fn ($pattern) => str_contains($source, $pattern) || str_contains($compiled, $pattern), ARRAY_FILTER_USE_KEY);
        $variables = implode("\n", $variables);

        return implode('', array_filter([
            $useStatements ? '<'.'?php '.$useStatements.' ?>' : null,
            '<'.'?php if (!function_exists(\''.$name.'\')):'."\n",
            'function '.$name.'($__blaze, $__data = [], $__slots = [], $__bound = [], $__this = null) {'."\n",
            $sourceUsesThis ? '$__blazeFn = function () use ($__blaze, $__data, $__slots, $__bound) {'."\n" : null,
            '$__env = $__blaze->env;'."\n",
            $needsEchoHandler ? '$__bladeCompiler = app(\'blade.compiler\');'."\n" : null,
            'if (($__data[\'attributes\'] ?? null) instanceof \Illuminate\View\ComponentAttributeBag) { $__data = $__data + $__data[\'attributes\']->all(); unset($__data[\'attributes\']); }'."\n",
            str_contains($source, '$slot') ? '$__slots[\'slot\'] ??= new \Illuminate\View\ComponentSlot(\'\');'."\n" : null,
            $variables,
            'extract($__slots, EXTR_SKIP);'."\n",
            'unset($__slots);'."\n",
            $propsExpression === null ? 'extract($__data, EXTR_SKIP);'."\n" : null,
            $awareAssignments,
            $needsEarlyAttributes ? '$attributes = new \\Livewire\\Blaze\\Runtime\\BlazeAttributeBag($__data);'."\n" : null,
            ($propsExpression === null && $sourceUsesAttributes) ? '$attributes = \\Livewire\\Blaze\\Runtime\\BlazeAttributeBag::sanitized($__data, $__bound);'."\n" : null,
            $propsExpression === null ? 'unset($__data, $__bound); ?>' : ' ?>',
            $compiled,
            $sourceUsesThis ? '<'.'?php };'."\n".'if ($__this !== null) { $__blazeFn->call($__this); } else { $__blazeFn(); }'."\n".'} endif; ?>' : null,
            !$sourceUsesThis ? '<'.'?php } endif; ?>' : null,
        ]));
    }

    /**
     * Extract PHP use statements from compiled output and return them separately.
     *
     * Handles both forms:
     * - @php use Foo\Bar; @endphp  → compiled as <?php use Foo\Bar; ?>
     * - @use('Foo\Bar')            → compiled as <?php use \Foo\Bar; ?>
     *
     * @return array{string, string|null} Tuple of [compiled without use statements, hoisted use statements]
     */
    /**
     * Extract PHP use statements from the source template and strip the
     * corresponding raw block placeholders from the compiled output.
     *
     * Use statements in Blade templates appear as either:
     * - @php use Foo\Bar; @endphp (inside @php blocks, possibly with other code)
     * - @use('Foo\Bar') or @use('Foo\Bar', 'Alias') (Blade directive)
     *
     * The @use directive is already stripped via stripDirective(). For @php
     * blocks, the use statements are hidden behind raw block placeholders in
     * $compiled, so we extract them from the original $source instead.
     *
     * @return array{string, string|null} Tuple of [compiled with use-only raw blocks removed, hoisted use statements]
     */
    protected function extractUseStatements(string $source, string $compiled): array
    {
        $useStatements = [];

        // Match use statements in the source template (inside @php blocks or bare PHP).
        $usePattern = '/^[ \t]*use\s+((?:function\s+|const\s+)?\\\\?[a-zA-Z_][a-zA-Z0-9_\\\\]*(?:\s+as\s+[a-zA-Z_][a-zA-Z0-9_]*)?)\s*;\s*$/m';

        preg_match_all($usePattern, $source, $matches);

        foreach ($matches[0] as $match) {
            $useStatements[] = trim($match);
        }

        // The @php blocks containing use statements are stored as raw block
        // placeholders (@__raw_block_N__@) in $compiled. We need to find
        // these placeholders and either remove them entirely (if the @php
        // block contained only use statements) or strip the use lines from
        // the restored content. Since raw blocks are opaque at this stage,
        // we restore them, strip the use lines, and re-store them.
        //
        // This also handles empty @php @endphp blocks (no use statements,
        // no other code) — their placeholders must be removed to avoid
        // leaving behind extra blank lines in the rendered output.
        $compiler = app('blade.compiler');
        $rawBlocksProperty = new \ReflectionProperty($compiler, 'rawBlocks');
        $rawBlocks = $rawBlocksProperty->getValue($compiler);

        foreach ($rawBlocks as $index => &$block) {
            // Strip use statements from the raw block content.
            $stripped = preg_replace($usePattern, '', $block);

            // If the block is now empty (only had use statements or was
            // already empty), remove the placeholder from compiled output.
            $strippedContent = trim(
                preg_replace('/^<\x3Fphp\s*/s', '',
                    preg_replace('/\s*\x3F>$/s', '', $stripped))
            );

            if ($strippedContent === '') {
                $compiled = preg_replace('/^[ \t]*@__raw_block_' . $index . '__@\s*/m', '', $compiled);
            } else {
                $block = $stripped;
            }
        }
        unset($block);

        $rawBlocksProperty->setValue($compiler, $rawBlocks);

        if (empty($useStatements)) {
            return [$compiled, null];
        }

        return [$compiled, implode("\n", $useStatements) . "\n"];
    }

    /**
     * Strip a directive and its surrounding whitespace from content.
     */
    protected function stripDirective(string $content, string $directive): string
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
     * Replace a directive with inline PHP code, preserving its position in the template.
     */
    protected function replaceDirectiveWithPhp(string $content, string $directive, string $phpCode): string
    {
        $content = preg_replace('/@__raw_block_(\d+)__@/', '__BLAZE_RAW_BLOCK_$1__', $content);

        $marker = '__BLAZE_INLINE_'.strtoupper($directive).'__';

        $content = BladeService::compileDirective($content, $directive, function () use ($marker) {
            return $marker;
        });

        $content = preg_replace(
            '/^[ \t]*'.preg_quote($marker, '/').'\s*/m',
            '<'.'?php '.rtrim($phpCode)." ?>\n",
            $content
        );

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
