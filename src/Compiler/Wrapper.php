<?php

namespace Livewire\Blaze\Compiler;

use Livewire\Blaze\BladeService;
use Livewire\Blaze\BlazeManager;
use Livewire\Blaze\Support\Utils;
use Livewire\Blaze\Parser\Nodes\DirectiveNode;
use Livewire\Blaze\Parser\Nodes\PhpBlockNode;
use Livewire\Blaze\Parser\Walker;

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
     * @param  array<\Livewire\Blaze\Parser\Nodes\Node>  $ast  The template AST
     * @param  string  $path  The component file path
     */
    public function wrap(array $ast, string $path): array
    {
        $name = ($this->manager->isFolding() ? '__' : '_') . Utils::hash($path);
        $sourceUsesThis = false;
        $imports = '';

        $ast = (new Walker)->walk(
            nodes: $ast,
            preCallback: function ($node) use (&$sourceUsesThis) {
                if (! $sourceUsesThis && $node->usesVariable('$this') || $node->isDirective(['entangle', 'script', 'assets'])) {
                    $sourceUsesThis = true;
                }

                if ($node instanceof DirectiveNode && $node->name === 'use') {
                    return new PhpBlockNode($this->blade->compileUseStatements($node->expression));
                }

                return $node;
            },
            postCallback: function ($node) use (&$imports) {
                if ($node instanceof PhpBlockNode) {
                    return new PhpBlockNode(
                        $this->useExtractor->extract($node->content, function ($statement) use (&$imports) {
                            $imports .= $statement . "\n";
                        })
                    );
                }

                if ($node instanceof DirectiveNode && $node->name === 'props') {
                    return new PhpBlockNode($this->propsCompiler->compile($node->expression));
                }

                if ($node instanceof DirectiveNode && $node->name === 'aware') {
                    return new PhpBlockNode($this->awareCompiler->compile($node->expression));
                }

                return $node;
            }
        );

        $opening = '';

        $opening .= '<'.'?php' . "\n";
        $opening .= $imports;
        $opening .= 'if (!function_exists(\''.$name.'\')):'."\n";
        $opening .= 'function '.$name.'($__blaze, $__data = [], $__slots = [], $__bound = [], $__keys = [], $__this = null) {'."\n";

        if ($sourceUsesThis) {
            $opening .= '$__blazeFn = function () use ($__blaze, $__data, $__slots, $__bound, $__keys) {'."\n";
        }

        $opening .= $this->globalVariables($ast)."\n";
        $opening .= 'if (($__data[\'attributes\'] ?? null) instanceof \Illuminate\View\ComponentAttributeBag) { $__data = $__data + $__data[\'attributes\']->all(); unset($__data[\'attributes\']); }'."\n";
        $opening .= 'extract($__slots, EXTR_SKIP); unset($__slots);'."\n";
        $opening .= 'extract($__data, EXTR_SKIP);'."\n";
        $opening .= '$attributes = \\Livewire\\Blaze\\Runtime\\BlazeAttributeBag::make($__data, $__bound, $__keys);'."\n";
        $opening .= 'unset($__data, $__bound, $__keys);'."\n";
        $opening .= 'ob_start();' . "\n";
        $opening .= '?>' . "\n";

        $closing = '<?php' . "\n";

        $contentHandler = $this->manager->isFolding() ? '$__blaze->processPassthroughContent(\'ltrim\', ltrim(ob_get_clean()))' : 'ltrim(ob_get_clean())';

        $closing .= 'echo ' . $contentHandler . ';' . "\n";

        if ($sourceUsesThis) {
            $closing .= '}; if ($__this !== null) { $__blazeFn->call($__this); } else { $__blazeFn(); }'."\n";
        }

        $closing .= '} endif; ?>';

        return [
            new PhpBlockNode($opening),
            ...$ast,
            new PhpBlockNode($closing),
        ];
    }
    
    protected function globalVariables(array $ast): string
    {
        $variables = [
            '$__env' => '$__env = $__blaze->env',
        ];

        $hasEchoHandlers = $this->blade->hasEchoHandlers();

        foreach ((new Walker)->iterate($ast) as $node) {
            if (! isset($variables['$app']) && $node->usesVariable('$app')) {
                $variables['$app'] = '$app = $__blaze->app';
            }

            if (! isset($variables['$errors']) && ($node->usesVariable('$errors') || $node->isDirective('error'))) {
                $variables['$errors'] = '$errors = $__blaze->errors';
            }

            if (! isset($variables['$__livewire']) && ($node->usesVariable('$__livewire') || $node->isDirective('entangle') || $node->isDirective('this'))) {
                $variables['$__livewire'] = '$__livewire = $__env->shared(\'__livewire\')';
            }

            if (! isset($variables['$_instance']) && $node->isDirective('this')) {
                $variables['$_instance'] = '$_instance = $__livewire';
            }

            if (! isset($variables['$slot']) && $node->usesVariable('$slot')) {
                $variables['$slot'] = '$__slots[\'slot\'] ??= new \Illuminate\View\ComponentSlot(\'\')';
            }

            if ($hasEchoHandlers && ! isset($variables['$__bladeCompiler']) && $node->usesEchoSyntax()) {
                $variables['$__bladeCompiler'] = '$__bladeCompiler = app(\'blade.compiler\')';
            }
        }

        return join(";\n", $variables);
    }
}
