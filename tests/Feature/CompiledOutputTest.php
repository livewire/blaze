<?php
use Livewire\Blaze\Compiler\TagCompiler as Compiler;
use Livewire\Blaze\Compiler\TagCompiler;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    app('blade.compiler')->anonymousComponentPath(__DIR__ . '/fixtures');
});

describe('call-site compilation', function () {
    it('compiles blaze component with static attributes', function () {
        $path = __DIR__ . '/fixtures/simple-button.blade.php';
        $hash = Compiler::hash($path);

        $result = app('blade.compiler')->compileString('<x-simple-button type="submit" class="btn-primary" />');

        expect($result)->toBe(
            '<?php $__blaze->ensureCompiled(\'' . $path . '\', __DIR__.\'/' . $hash . '.php\'); ?>' . "\n" .
            '<?php require_once __DIR__.\'/' . $hash . '.php\'; ?>' . "\n" .
            '<?php $__blaze->pushData([\'type\' => \'submit\',\'class\' => \'btn-primary\']); ?>' . "\n" .
            '<?php _' . $hash . '($__blaze, [\'type\' => \'submit\',\'class\' => \'btn-primary\'], [], []); ?>' . "\n" .
            '<?php $__blaze->popData(); ?>' . "\n"
        );
    });

    it('compiles blaze component with dynamic attributes', function () {
        $path = __DIR__ . '/fixtures/simple-button.blade.php';
        $hash = Compiler::hash($path);

        $result = app('blade.compiler')->compileString('<x-simple-button :type="$buttonType" :class="$classes" />');

        expect($result)->toBe(
            '<?php $__blaze->ensureCompiled(\'' . $path . '\', __DIR__.\'/' . $hash . '.php\'); ?>' . "\n" .
            '<?php require_once __DIR__.\'/' . $hash . '.php\'; ?>' . "\n" .
            '<?php $__blaze->pushData([\'type\' => $buttonType,\'class\' => $classes]); ?>' . "\n" .
            '<?php _' . $hash . '($__blaze, [\'type\' => $buttonType,\'class\' => $classes], [], [\'type\', \'class\']); ?>' . "\n" .
            '<?php $__blaze->popData(); ?>' . "\n"
        );
    });

    it('compiles blaze component with shorthand dynamic attributes', function () {
        $path = __DIR__ . '/fixtures/simple-button.blade.php';
        $hash = Compiler::hash($path);

        $result = app('blade.compiler')->compileString('<x-simple-button :$type :$class />');

        expect($result)->toBe(
            '<?php $__blaze->ensureCompiled(\'' . $path . '\', __DIR__.\'/' . $hash . '.php\'); ?>' . "\n" .
            '<?php require_once __DIR__.\'/' . $hash . '.php\'; ?>' . "\n" .
            '<?php $__blaze->pushData([\'type\' => $type,\'class\' => $class]); ?>' . "\n" .
            '<?php _' . $hash . '($__blaze, [\'type\' => $type,\'class\' => $class], [], [\'type\', \'class\']); ?>' . "\n" .
            '<?php $__blaze->popData(); ?>' . "\n"
        );
    });

    it('compiles blaze component with no attributes', function () {
        $path = __DIR__ . '/fixtures/simple-button.blade.php';
        $hash = Compiler::hash($path);

        $result = app('blade.compiler')->compileString('<x-simple-button />');

        expect($result)->toBe(
            '<?php $__blaze->ensureCompiled(\'' . $path . '\', __DIR__.\'/' . $hash . '.php\'); ?>' . "\n" .
            '<?php require_once __DIR__.\'/' . $hash . '.php\'; ?>' . "\n" .
            '<?php $__blaze->pushData([]); ?>' . "\n" .
            '<?php _' . $hash . '($__blaze, [], [], []); ?>' . "\n" .
            '<?php $__blaze->popData(); ?>' . "\n"
        );
    });

    it('compiles blaze component with boolean attributes', function () {
        $path = __DIR__ . '/fixtures/simple-button.blade.php';

        $result = app('blade.compiler')->compileString('<x-simple-button disabled required />');

        expect($result)->toContain("'disabled' => true");
        expect($result)->toContain("'required' => true");
    });

    it('compiles blaze component with wire and alpine attributes', function () {
        $path = __DIR__ . '/fixtures/simple-button.blade.php';
        $hash = Compiler::hash($path);

        $result = app('blade.compiler')->compileString('<x-simple-button wire:click="save" x-on:click="open = true" @click="handle" />');

        expect($result)->toBe(
            '<?php $__blaze->ensureCompiled(\'' . $path . '\', __DIR__.\'/' . $hash . '.php\'); ?>' . "\n" .
            '<?php require_once __DIR__.\'/' . $hash . '.php\'; ?>' . "\n" .
            '<?php $__blaze->pushData([\'wire:click\' => \'save\',\'x-on:click\' => \'open = true\',\'@click\' => \'handle\']); ?>' . "\n" .
            '<?php _' . $hash . '($__blaze, [\'wire:click\' => \'save\',\'x-on:click\' => \'open = true\',\'@click\' => \'handle\'], [], []); ?>' . "\n" .
            '<?php $__blaze->popData(); ?>' . "\n"
        );
    });

    it('does not transform non-blaze components', function () {
        $result = app('blade.compiler')->compileString('<x-non-blaze-button type="submit" />');

        expect($result)->not->toContain('$__blaze->ensureCompiled');
        expect($result)->toContain('Illuminate\View\AnonymousComponent');
    });

    it('preserves surrounding html content', function () {
        $result = app('blade.compiler')->compileString('<div>Before<x-simple-button />After</div>');

        expect($result)->toContain('<div>Before');
        expect($result)->toContain('After</div>');
        expect($result)->toContain('$__blaze->ensureCompiled');
    });

    it('ends with trailing newline to prevent PHP from swallowing content newlines', function () {
        $result = app('blade.compiler')->compileString('<x-simple-button />');

        expect($result)->toEndWith("?>\n");
    });

    it('wraps @aware component call with pushData/popData', function () {
        $path = __DIR__ . '/fixtures/aware-menu.blade.php';
        $hash = Compiler::hash($path);
        
        $result = app('blade.compiler')->compileString('<x-aware-menu color="blue" size="lg">Content</x-aware-menu>');

        expect($result)
            ->toContain("\$__blaze->pushData(['color' => 'blue','size' => 'lg'])")
            ->toContain('_' . $hash . '($__blaze')
            ->toContain('$__blaze->popData()');

        // Verify order: pushData -> component call -> popData
        $pushPos = strpos($result, 'pushData');
        $callPos = strpos($result, '_' . $hash . '($__blaze');
        $popPos = strpos($result, 'popData');

        expect($pushPos)->toBeLessThan($callPos);
        expect($callPos)->toBeLessThan($popPos);
    });

    it('compiles component with default slot', function () {
        $path = __DIR__ . '/fixtures/card.blade.php';
        $hash = Compiler::hash($path);

        $result = app('blade.compiler')->compileString('<x-card>Hello World</x-card>');

        expect($result)
            ->toContain("\$__blaze->ensureCompiled('{$path}', __DIR__.'/$hash.php')")
            ->toContain("require_once __DIR__.'/$hash.php'")
            ->toContain("\$slot$hash = []")
            ->toContain('ob_start()')
            ->toContain("['slot'] = new \\Illuminate\\View\\ComponentSlot(trim(ob_get_clean()), [])")
            ->toContain("_$hash(\$__blaze, [], \$slot$hash, [])");
    });

    it('compiles component with named slot using short syntax', function () {
        $result = app('blade.compiler')->compileString('<x-card-header><x-slot:header>Header Content</x-slot:header>Body</x-card-header>');

        expect($result)
            ->toContain("['header'] = new \\Illuminate\\View\\ComponentSlot(trim(ob_get_clean())")
            ->toContain("['slot'] = new \\Illuminate\\View\\ComponentSlot(trim(ob_get_clean()), [])");
    });

    it('compiles component with named slot using standard syntax', function () {
        $result = app('blade.compiler')->compileString('<x-card-header><x-slot name="header">Header Content</x-slot>Body</x-card-header>');

        expect($result)->toContain("['header'] = new \\Illuminate\\View\\ComponentSlot(trim(ob_get_clean())");
    });

    it('compiles slot with static attributes', function () {
        $result = app('blade.compiler')->compileString('<x-card-with-attributes><x-slot:header class="bold">Header</x-slot:header>Body</x-card-with-attributes>');

        expect($result)->toContain("'class' => 'bold'");
    });

    it('compiles slot with dynamic attributes', function () {
        $result = app('blade.compiler')->compileString('<x-card-with-attributes><x-slot:header :class="$classes">Header</x-slot:header>Body</x-card-with-attributes>');

        expect($result)->toContain('$classes');
    });

    it('compiles self-closing component without slots parameter', function () {
        $hash = Compiler::hash(__DIR__ . '/fixtures/simple-button.blade.php');

        $result = app('blade.compiler')->compileString('<x-simple-button />');

        expect($result)
            ->not->toContain('$slot')
            ->not->toContain('ob_start')
            ->toContain("_$hash(\$__blaze, [], [], [])");
    });

    it('converts kebab-case slot name to camelCase', function () {
        $result = app('blade.compiler')->compileString('<x-card><x-slot:card-header>Header</x-slot:card-header></x-card>');

        expect($result)->toContain("['cardHeader'] = new \\Illuminate\\View\\ComponentSlot");
    });

    it('falls back to Laravel for dynamic slot names', function () {
        $result = app('blade.compiler')->compileString('<x-card><x-slot :name="$slotName">Content</x-slot></x-card>');

        expect($result)
            ->not->toContain('$__blaze->ensureCompiled')
            ->toContain('Illuminate\View\AnonymousComponent');
    });

    it('does not include sanitizeComponentAttribute in component call-site', function () {
        $result = app('blade.compiler')->compileString('<x-simple-button :type="$type" />');

        expect($result)->not->toContain('sanitizeComponentAttribute');
        expect($result)->toContain("'type' => \$type");
    });

    it('includes sanitizeComponentAttribute in slot attributes', function () {
        $result = app('blade.compiler')->compileString('<x-card-with-attributes><x-slot:header :class="$classes">Header</x-slot:header>Body</x-card-with-attributes>');

        expect($result)->toContain('sanitizeComponentAttribute');
    });
});

describe('component wrapper compilation', function () {
    it('compiles blaze component source into function wrapper', function () {
        $path = __DIR__ . '/fixtures/simple-button.blade.php';
        $hash = Compiler::hash($path);
        $compiled = compile('simple-button.blade.php');

        expect($compiled)->toBe(
            '<?php if (!function_exists(\'_' . $hash . '\')):
function _' . $hash . '($__blaze, $__data = [], $__slots = [], $__bound = []) {
$__env = $__blaze->env;
extract($__data, EXTR_SKIP);
$attributes = \Livewire\Blaze\Runtime\BlazeAttributeBag::sanitized($__data, $__bound);
unset($__data, $__bound);
extract($__slots, EXTR_SKIP);
unset($__slots); ?><button <?php echo e($attributes); ?>>Click</button><?php }
endif; ?><?php /**PATH ' . $path . ' ENDPATH**/ ?>'
        );
    });

    it('generates @aware lookup code', function () {
        $compiled = compile('aware-menu-item.blade.php');

        expect($compiled)->toContain("\$color = \$__blaze->getConsumableData('color', 'gray')");
    });

    it('generates props code for component with defaults', function () {
        $path = __DIR__ . '/fixtures/props-button.blade.php';
        $hash = Compiler::hash($path);
        $compiled = compile('props-button.blade.php');

        expect($compiled)->toContain("function _$hash(\$__blaze, \$__data = [], \$__slots = [], \$__bound = [])");
        expect($compiled)->toContain("\$__defaults = ['type' => 'button', 'disabled' => false];");
        expect($compiled)->toContain("\$type = \$__data['type'] ?? \$__defaults['type'];");
        expect($compiled)->toContain("\$disabled = \$__data['disabled'] ?? \$__defaults['disabled'];");
        expect($compiled)->toContain("unset(\$__defaults);");
        expect($compiled)->toContain("unset(\$__data['type']);");
        expect($compiled)->toContain("unset(\$__data['disabled']);");
        expect($compiled)->toContain('$attributes = \Livewire\Blaze\Runtime\BlazeAttributeBag::sanitized($__data, $__bound);');
        expect($compiled)->toContain('unset($__data, $__bound);');
        expect($compiled)->not->toContain('@props');
    });

    it('generates array_key_exists for required props', function () {
        $compiled = compile('props-required.blade.php');

        expect($compiled)->toContain("if (array_key_exists('label', \$__data)) { \$label = \$__data['label']; }");
    });

    it('generates both camelCase and kebab-case in unset', function () {
        $compiled = compile('props-camel.blade.php');

        expect($compiled)->toContain("\$__data['backgroundColor']");
        expect($compiled)->toContain("\$__data['background-color']");
    });

    it('creates $attributes early only when referenced in @props', function () {
        $compiled = compile('props-attrs-ref.blade.php');

        $firstAttributesPos = strpos($compiled, '$attributes = new \Livewire\Blaze\Runtime\BlazeAttributeBag($__data);');
        $defaultsPos = strpos($compiled, '$__defaults = ');

        expect($firstAttributesPos)->toBeLessThan($defaultsPos);
        expect($compiled)->toContain('$attributes = new \Livewire\Blaze\Runtime\BlazeAttributeBag($__data);');
        expect($compiled)->not->toContain('$attributes = \Livewire\Blaze\Runtime\BlazeAttributeBag::sanitized($__data, $__bound);');
    });

    it('does not create early $attributes when not referenced in @props', function () {
        $compiled = compile('props-button.blade.php');

        expect($compiled)->not->toContain('$attributes = new \Livewire\Blaze\Runtime\BlazeAttributeBag($__data);');
        expect($compiled)->toContain('$attributes = \Livewire\Blaze\Runtime\BlazeAttributeBag::sanitized($__data, $__bound);');
    });

    it('generates extract() call when no @props directive', function () {
        $compiled = compile('no-props.blade.php');

        expect($compiled)->toContain('extract($__data, EXTR_SKIP);');
        expect($compiled)->not->toContain('$attributes = \Livewire\Blaze\Runtime\BlazeAttributeBag::sanitized($__data, $__bound);');
    });

    it('does not generate extract() call when @props directive is present', function () {
        $compiled = compile('props-simple.blade.php');

        expect($compiled)->not->toContain('extract($__data, EXTR_SKIP);');
        expect($compiled)->toContain("\$foo = \$__data['foo']");
    });

    it('includes $__slots parameter in function signature', function () {
        $hash = Compiler::hash(__DIR__ . '/fixtures/card.blade.php');
        $compiled = compile('card.blade.php');

        expect($compiled)
            ->toContain("function _$hash(\$__blaze, \$__data = [], \$__slots = [], \$__bound = [])")
            ->toContain('extract($__slots, EXTR_SKIP)')
            ->toContain('unset($__slots)');
    });

    it('uses sanitized() factory method for $attributes', function () {
        $compiled = compile('simple-button.blade.php');

        expect($compiled)->toContain('BlazeAttributeBag::sanitized($__data, $__bound)');
        expect($compiled)->not->toContain('new \\Livewire\\Blaze\\Runtime\\BlazeAttributeBag($__data)');
    });
});
