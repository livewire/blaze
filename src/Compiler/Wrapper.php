<?php

namespace Livewire\Blaze\Compiler;

use Livewire\Blaze\BladeService;
use Livewire\Blaze\Support\Utils;
use Livewire\Blaze\Blaze;
use Illuminate\Support\Arr;

/**
 * Compiles Blaze component templates into PHP function definitions.
 */
class Wrapper
{
    public function __construct(
        protected PropsCompiler $propsCompiler = new PropsCompiler,
        protected AwareCompiler $awareCompiler = new AwareCompiler,
        protected UseExtractor $useExtractor = new UseExtractor,
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

        $sourceUsesThis = str_contains($source, '$this') || str_contains($compiled, '@entangle') || str_contains($compiled, '@script');

        $compiled = BladeService::compileUseStatements($compiled);
        $compiled = BladeService::restoreRawBlocks($compiled);
        $compiled = BladeService::storeVerbatimBlocks($compiled);

        $imports = '';
        
        $compiled = $this->useExtractor->extract($compiled, function ($statement) use (&$imports) {
            $imports .= $statement . "\n";
        });

        $compiled = BladeService::preStoreUncompiledBlocks($compiled);

        $output = '';

        $output .= '<'.'?php' . "\n";
        $output .= $imports;
        $output .= 'if (!function_exists(\''.$name.'\')):'."\n";
        $output .= 'function '.$name.'($__blaze, $__data = [], $__slots = [], $__bound = [], $__this = null) {'."\n";

        if ($sourceUsesThis) {
            $output .= '$__blazeFn = function () use ($__blaze, $__data, $__slots, $__bound) {'."\n";
        }

        $output .= $this->globalVariables($source, $compiled);

        if (
            config('blaze.view_composers', false)
            && ! Blaze::isFolding()
            && ($componentName = \Livewire\Blaze\BladeService::pathToComponentName($path))
        ) {
            $output .= '$__data = $__blaze->callComposers('.var_export($componentName, true).', $__data);'."\n";
        }

        $output .= 'if (($__data[\'attributes\'] ?? null) instanceof \Illuminate\View\ComponentAttributeBag) { $__data = $__data + $__data[\'attributes\']->all(); unset($__data[\'attributes\']); }'."\n";
        $output .= '$attributes = \\Livewire\\Blaze\\Runtime\\BlazeAttributeBag::sanitized($__data, $__bound);'."\n";
        $output .= 'extract($__slots, EXTR_SKIP); unset($__slots);'."\n";
        $output .= 'extract($__data, EXTR_SKIP); unset($__data, $__bound);'."\n";
        $output .= 'ob_start();' . "\n";
        $output .= '?>' . "\n";

        $compiled = BladeService::compileDirective($compiled, 'props', $this->propsCompiler->compile(...));
        $compiled = BladeService::compileDirective($compiled, 'aware', $this->awareCompiler->compile(...));
        $compiled = BladeService::restoreRawBlocks($compiled);

        $output .= $compiled;

        $output .= '<?php' . "\n";

        $output .= 'echo ltrim(ob_get_clean());' . "\n";

        if ($sourceUsesThis) {
            $output .= '}; if ($__this !== null) { $__blazeFn->call($__this); } else { $__blazeFn(); }'."\n";
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
