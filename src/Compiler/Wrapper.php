<?php

namespace Livewire\Blaze\Compiler;

use Livewire\Blaze\BladeService;
use Livewire\Blaze\BlazeManager;
use Livewire\Blaze\Compiler\DirectiveCompiler;
use Livewire\Blaze\Support\Utils;
use Illuminate\Support\Arr;

/**
 * Compiles Blaze component templates into PHP function definitions.
 */
class Wrapper
{
    protected PropsCompiler $propsCompiler;
    protected AwareCompiler $awareCompiler;
    protected UseExtractor $useExtractor;

    public function __construct(
        protected BladeService $blade,
        protected BlazeManager $manager,
    ) {
        $this->propsCompiler = new PropsCompiler;
        $this->awareCompiler = new AwareCompiler;
        $this->useExtractor = new UseExtractor;
    }

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
        $name = ($this->manager->isFolding() ? '__' : '_') . Utils::hash($path);

        $sourceUsesThis = str_contains($source, '$this') || str_contains($compiled, '@entangle') || str_contains($compiled, '@script') || str_contains($compiled, '@assets');
        $sourceUsesProps = str_contains($source, '@props') || str_contains($compiled, '@props');

        $compiled = $this->blade->compileUseStatements($compiled);
        $compiled = $this->blade->restoreRawBlocks($compiled);
        $compiled = $this->blade->storeVerbatimBlocks($compiled);

        $imports = '';
        
        $compiled = $this->useExtractor->extract($compiled, function ($statement) use (&$imports) {
            $imports .= $statement . "\n";
        });

        $compiled = $this->blade->preStoreUncompiledBlocks($compiled);

        $output = '';

        $output .= '<'.'?php' . "\n";
        $output .= $imports;
        $output .= 'if (!function_exists(\''.$name.'\')):'."\n";
        $output .= 'function '.$name.'($__blaze, $__data = [], $__slots = [], $__bound = [], $__keys = [], $__this = null, $__view = false) {'."\n";

        if ($sourceUsesThis) {
            $output .= '$__blazeFn = function () use ($__blaze, $__data, $__slots, $__bound, $__keys, $__view) {'."\n";
        }

        $output .= 'if ($__view):'."\n";
        $output .= '$__bladeCompiler = $__blaze->compiler;' . "\n";

        if ($sourceUsesProps) {
            $output .= 'if (($__data[\'attributes\'] ?? null) instanceof \Illuminate\View\ComponentAttributeBag) {'."\n";
            $output .= '$attributes = new \Illuminate\View\ComponentAttributeBag($__data[\'attributes\']->all()); unset($__data[\'attributes\']);'."\n";
            $output .= '} else {'."\n";
            $output .= '$attributes ??= new \Illuminate\View\ComponentAttributeBag([]);'."\n";
            $output .= '}'."\n";
        }

        $output .= 'extract($__data, EXTR_SKIP);'."\n";

        $output .= 'else:'."\n";
        $output .= $this->globalVariables($source, $compiled);
        $output .= 'if (($__data[\'attributes\'] ?? null) instanceof \Illuminate\View\ComponentAttributeBag) { $__data = $__data + $__data[\'attributes\']->all(); unset($__data[\'attributes\']); }'."\n";
        $output .= 'extract($__slots, EXTR_SKIP); unset($__slots);'."\n";
        $output .= 'extract($__data, EXTR_SKIP);'."\n";
        $output .= '$attributes = \\Livewire\\Blaze\\Runtime\\BlazeAttributeBag::make($__data, $__bound, $__keys);'."\n";
        $output .= 'unset($__data, $__bound, $__keys);'."\n";
        $output .= 'ob_start();' . "\n";
        $output .= 'endif;'."\n";
        $output .= '?>' . "\n";

        $compiled = DirectiveCompiler::make()
            ->directive('props', $this->propsCompiler->compile(...))
            ->directive('aware', $this->awareCompiler->compile(...))
            ->compile($compiled);

        $compiled = $this->blade->restoreRawBlocks($compiled);

        $output .= $compiled;

        $output .= '<?php' . "\n";

        $contentHandler = $this->manager->isFolding() ? '$__blaze->processPassthroughContent(\'ltrim\', ltrim(ob_get_clean()))' : 'ltrim(ob_get_clean())';

        $output .= 'if (!$__view) { echo ' . $contentHandler . '; }'."\n";

        if ($sourceUsesThis) {
            $output .= '}; if ($__this !== null) { $__blazeFn->call($__this); } else { $__blazeFn(); }'."\n";
        }

        $output .= '} endif;'."\n";
        $output .= 'if (isset($__path) && ($__path === __FILE__ || realpath($__path) === __FILE__)) {'."\n";
        $output .= $name.'($__blaze, $__data, [], [], [], $__this ?? null, true);'."\n";
        $output .= '}'."\n";
        $output .= '?>';

        return $output;
    }
    
    protected function globalVariables(string $source, string $compiled): string
    {
        $output = '';

        $output .= '$__env = $__blaze->env;' . "\n";

        if ($this->hasEchoHandlers() && ($this->hasEchoSyntax($source) || $this->hasEchoSyntax($compiled))) {
            $output .= '$__bladeCompiler = $__blaze->compiler;' . "\n";
        }

        $output .= implode("\n", array_filter(Arr::map([
            [['$app'], '$app = $__blaze->app;'],
            [['$errors', '@error'], '$errors = $__blaze->errors;'],
            [['$__livewire', '@entangle', '@this'], '$__livewire = $__env->shared(\'__livewire\');'],
            [['@this'], '$_instance = $__livewire;'],
            [['$slot'], '$__slots[\'slot\'] ??= new \Illuminate\View\ComponentSlot(\'\');'],
        ], function ($data) use ($source, $compiled) {
            [$patterns, $variable] = $data;

            foreach ($patterns as $pattern) {
                if (str_contains($source, $pattern) || str_contains($compiled, $pattern)) {
                    return $variable;
                }
            }

            return null;
        }))) . "\n";

        return $output;
    }

    /**
     * Check if the Blade compiler has any echo handlers registered.
     */
    protected function hasEchoHandlers(): bool
    {
        $compiler = $this->blade->compiler;
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
