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
        // the function definition. First restore any raw block placeholders
        // (from @php blocks) so all PHP is visible, then protect @verbatim
        // blocks from modification, compile @use directives into PHP, and
        // extract all use statements uniformly.
        $compiled = BladeService::restoreRawBlocks($compiled);
        $compiled = BladeService::storeVerbatimBlocks($compiled);
        $compiled = $this->compileUseDirective($compiled);
        [$compiled, $useStatements] = $this->extractUseStatements($compiled);

        $needsEchoHandler = $this->hasEchoHandlers() && $this->hasEchoSyntax($source);

        $sourceUsesThis = str_contains($source, '$this') || str_contains($compiled, '@script');

        $variables = [
            '$app' => '$app = $__blaze->app;',
            '$errors' => '$errors = $__blaze->errors;',
            '@error' => '$errors = $__blaze->errors;',
            '$__livewire' => '$__livewire = $__env->shared(\'__livewire\');',
            '@entangle' => '$__livewire = $__env->shared(\'__livewire\');',
        ];

        $variables = array_filter($variables, fn ($pattern) => str_contains($source, $pattern) || str_contains($compiled, $pattern), ARRAY_FILTER_USE_KEY);
        $variables = implode("\n", $variables);

        $output = implode('', array_filter([
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

        // Restore @verbatim blocks that were protected during use statement extraction.
        return BladeService::restoreRawBlocks($output);
    }

    /**
     * Compile @use directives into PHP use statement blocks.
     */
    protected function compileUseDirective(string $content): string
    {
        return BladeService::compileDirective($content, 'use', function ($expression) {
            $segments = explode(',', preg_replace("/[\(\)]/", '', $expression));
            $use = ltrim(trim($segments[0], " '\""), '\\');
            $as = isset($segments[1]) ? ' as '.trim($segments[1], " '\"") : '';

            return '<'."?php use {$use}{$as}; ?>";
        });
    }

    /**
     * Extract PHP use statements from compiled output and return them separately.
     *
     * At this point raw blocks have been restored and @verbatim blocks are
     * protected behind placeholders, so all use statements are visible as
     * plain `use Foo\Bar;` lines inside PHP blocks.
     *
     * @return array{string, string|null} Tuple of [compiled without use statements, hoisted use statements]
     */
    protected function extractUseStatements(string $compiled): array
    {
        $useStatements = [];

        $useFragment = 'use\s+((?:function\s+|const\s+)?\\\\?[a-zA-Z_][a-zA-Z0-9_\\\\]*(?:\s+as\s+[a-zA-Z_][a-zA-Z0-9_]*)?)\s*;';

        // Pass 1: Remove complete PHP blocks that contain only use statements.
        // Use statements never contain string literals, so matching the
        // closing tag is unambiguous here.
        $compiled = preg_replace_callback(
            '/<\?php(\s*'.$useFragment.'\s*)+\?>/s',
            function ($match) use (&$useStatements, $useFragment) {
                preg_match_all('/'.$useFragment.'/', $match[0], $uses);

                foreach ($uses[0] as $use) {
                    $useStatements[] = trim($use);
                }

                return '';
            },
            $compiled,
        );

        // Pass 2: Extract bare use lines from mixed PHP blocks (blocks that
        // contain other code alongside use statements).
        $compiled = preg_replace_callback(
            '/^[ \t]*'.$useFragment.'\s*$/m',
            function ($match) use (&$useStatements) {
                $useStatements[] = trim($match[0]);

                return '';
            },
            $compiled,
        );

        if (empty($useStatements)) {
            return [$compiled, null];
        }

        return [$compiled, implode("\n", $useStatements)];
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
