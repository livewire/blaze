<?php
use Livewire\Blaze\Support\Utils;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    app('blade.compiler')->anonymousComponentPath(__DIR__ . '/fixtures');
});

describe('call-site compilation', function () {
    it('compiles blaze component with static attributes', function () {
        $path = __DIR__ . '/fixtures/simple-button.blade.php';
        $hash = Utils::hash($path);

        $result = app('blade.compiler')->compileString('<x-simple-button type="submit" class="btn-primary" />');

        expect($result)->toBe(
            '<?php $__blaze->ensureCompiled(\'' . $path . '\', $__blaze->compiledPath.\'/' . $hash . '.php\'); ?>' . "\n" .
            '<?php require_once $__blaze->compiledPath.\'/' . $hash . '.php\'; ?>' . "\n" .
            '<?php $__blaze->pushData([\'type\' => \'submit\',\'class\' => \'btn-primary\']); ?>' . "\n" .
            '<?php _' . $hash . '($__blaze, [\'type\' => \'submit\',\'class\' => \'btn-primary\'], [], []); ?>' . "\n" .
            '<?php $__blaze->popData(); ?>' . "\n"
        );
    });

    it('compiles blaze component with dynamic attributes', function () {
        $path = __DIR__ . '/fixtures/simple-button.blade.php';
        $hash = Utils::hash($path);

        $result = app('blade.compiler')->compileString('<x-simple-button :type="$buttonType" :class="$classes" />');

        expect($result)->toBe(
            '<?php $__blaze->ensureCompiled(\'' . $path . '\', $__blaze->compiledPath.\'/' . $hash . '.php\'); ?>' . "\n" .
            '<?php require_once $__blaze->compiledPath.\'/' . $hash . '.php\'; ?>' . "\n" .
            '<?php $__blaze->pushData([\'type\' => $buttonType,\'class\' => $classes]); ?>' . "\n" .
            '<?php _' . $hash . '($__blaze, [\'type\' => $buttonType,\'class\' => $classes], [], [\'type\', \'class\']); ?>' . "\n" .
            '<?php $__blaze->popData(); ?>' . "\n"
        );
    });

    it('compiles blaze component with shorthand dynamic attributes', function () {
        $path = __DIR__ . '/fixtures/simple-button.blade.php';
        $hash = Utils::hash($path);

        $result = app('blade.compiler')->compileString('<x-simple-button :$type :$class />');

        expect($result)->toBe(
            '<?php $__blaze->ensureCompiled(\'' . $path . '\', $__blaze->compiledPath.\'/' . $hash . '.php\'); ?>' . "\n" .
            '<?php require_once $__blaze->compiledPath.\'/' . $hash . '.php\'; ?>' . "\n" .
            '<?php $__blaze->pushData([\'type\' => $type,\'class\' => $class]); ?>' . "\n" .
            '<?php _' . $hash . '($__blaze, [\'type\' => $type,\'class\' => $class], [], [\'type\', \'class\']); ?>' . "\n" .
            '<?php $__blaze->popData(); ?>' . "\n"
        );
    });

    it('compiles blaze component with no attributes', function () {
        $path = __DIR__ . '/fixtures/simple-button.blade.php';
        $hash = Utils::hash($path);

        $result = app('blade.compiler')->compileString('<x-simple-button />');

        expect($result)->toBe(
            '<?php $__blaze->ensureCompiled(\'' . $path . '\', $__blaze->compiledPath.\'/' . $hash . '.php\'); ?>' . "\n" .
            '<?php require_once $__blaze->compiledPath.\'/' . $hash . '.php\'; ?>' . "\n" .
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
        $hash = Utils::hash($path);

        $result = app('blade.compiler')->compileString('<x-simple-button wire:click="save" x-on:click="open = true" @click="handle" />');

        expect($result)->toBe(
            '<?php $__blaze->ensureCompiled(\'' . $path . '\', $__blaze->compiledPath.\'/' . $hash . '.php\'); ?>' . "\n" .
            '<?php require_once $__blaze->compiledPath.\'/' . $hash . '.php\'; ?>' . "\n" .
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
        $hash = Utils::hash($path);
        
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
        $hash = Utils::hash($path);

        $result = app('blade.compiler')->compileString('<x-card>Hello World</x-card>');

        expect($result)
            ->toContain("\$__blaze->ensureCompiled('{$path}', \$__blaze->compiledPath.'/$hash.php')")
            ->toContain("require_once \$__blaze->compiledPath.'/$hash.php'")
            ->toContain("\$slots$hash = []")
            ->toContain('ob_start()')
            ->toContain("['slot'] = new \\Illuminate\\View\\ComponentSlot(trim(ob_get_clean()), [])")
            ->toContain("_$hash(\$__blaze, [], \$slots$hash, [])");
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
        $hash = Utils::hash(__DIR__ . '/fixtures/simple-button.blade.php');

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
        $hash = Utils::hash($path);
        $compiled = compile('simple-button.blade.php');

        expect($compiled)->toBe(
            '<?php if (!function_exists(\'_' . $hash . '\')):
function _' . $hash . '($__blaze, $__data = [], $__slots = [], $__bound = []) {
$__env = $__blaze->env;
if (($__data[\'attributes\'] ?? null) instanceof \Illuminate\View\ComponentAttributeBag) { $__data = $__data + $__data[\'attributes\']->all(); unset($__data[\'attributes\']); }
extract($__slots, EXTR_SKIP);
unset($__slots);
extract($__data, EXTR_SKIP);
$attributes = \Livewire\Blaze\Runtime\BlazeAttributeBag::sanitized($__data, $__bound);
unset($__data, $__bound); ?><button <?php echo e($attributes); ?>>Click</button><?php } endif; ?><?php /**PATH ' . $path . ' ENDPATH**/ ?>'
        );
    });

    it('generates @aware lookup code', function () {
        $compiled = compile('aware-menu-item.blade.php');

        expect($compiled)->toContain("\$color = \$__blaze->getConsumableData('color', \$__awareDefaults['color'])");
    });

    it('generates props code for component with defaults', function () {
        $path = __DIR__ . '/fixtures/props-button.blade.php';
        $hash = Utils::hash($path);
        $compiled = compile('props-button.blade.php');

        expect($compiled)->toContain("function _$hash(\$__blaze, \$__data = [], \$__slots = [], \$__bound = [])");
        expect($compiled)->toContain("\$__defaults = ['type' => 'button', 'disabled' => false];");
        expect($compiled)->toContain("\$type ??= \$__data['type'] ?? \$__defaults['type'];");
        expect($compiled)->toContain("\$disabled ??= \$__data['disabled'] ?? \$__defaults['disabled'];");
        expect($compiled)->toContain("unset(\$__defaults);");
        expect($compiled)->toContain("unset(\$__data['type']);");
        expect($compiled)->toContain("unset(\$__data['disabled']);");
        expect($compiled)->toContain('$attributes = \Livewire\Blaze\Runtime\BlazeAttributeBag::sanitized($__data, $__bound);');
        expect($compiled)->toContain('unset($__data, $__bound);');
        expect($compiled)->not->toContain('@props');
    });

    it('generates array_key_exists for required props', function () {
        $compiled = compile('props-required.blade.php');

        expect($compiled)->toContain("if (!isset(\$label) && array_key_exists('label', \$__data)) { \$label = \$__data['label']; }");
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

    it('creates sanitized $attributes when referenced in @props and template', function () {
        $compiled = compile('props-attrs-both.blade.php');

        $earlyAttributesPos = strpos($compiled, '$attributes = new \Livewire\Blaze\Runtime\BlazeAttributeBag($__data);');
        $sanitizedPos = strpos($compiled, '$attributes = \Livewire\Blaze\Runtime\BlazeAttributeBag::sanitized($__data, $__bound);');

        expect($earlyAttributesPos)->not->toBeFalse();
        expect($sanitizedPos)->not->toBeFalse();
        expect($earlyAttributesPos)->toBeLessThan($sanitizedPos);
    });

    it('does not create early $attributes when not referenced in @props', function () {
        $compiled = compile('props-button.blade.php');

        expect($compiled)->not->toContain('$attributes = new \Livewire\Blaze\Runtime\BlazeAttributeBag($__data);');
        expect($compiled)->toContain('$attributes = \Livewire\Blaze\Runtime\BlazeAttributeBag::sanitized($__data, $__bound);');
    });

    it('detects $attributes usage inside @php blocks', function () {
        $compiled = compile('attrs-in-php-block.blade.php');

        // The $attributes variable should be initialized even though it's only used in a @php block
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
        expect($compiled)->toContain("\$foo ??= \$__data['foo']");
    });

    it('includes $__slots parameter in function signature', function () {
        $hash = Utils::hash(__DIR__ . '/fixtures/card.blade.php');
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

describe('delegate component compilation', function () {
    beforeEach(function () {
        app('blade.compiler')->anonymousComponentPath(__DIR__ . '/fixtures/delegate');
    });

    it('compiles delegate-component with slot content', function () {
        $path = __DIR__ . '/fixtures/delegate/delegate-parent.blade.php';
        $hash = Utils::hash($path);
        $compiled = compile('delegate/delegate-parent.blade.php');

        // The slots variable name is a hash of the component expression
        $slotsHash = hash('xxh128', "'flux::' . 'child.' . \$variant");

        expect($compiled)->toBe(
            '<?php if (!function_exists(\'_' . $hash . '\')):
function _' . $hash . '($__blaze, $__data = [], $__slots = [], $__bound = []) {
$__env = $__blaze->env;
if (($__data[\'attributes\'] ?? null) instanceof \Illuminate\View\ComponentAttributeBag) { $__data = $__data + $__data[\'attributes\']->all(); unset($__data[\'attributes\']); }
extract($__slots, EXTR_SKIP);
unset($__slots);
$__defaults = [\'variant\' => \'default\'];
$variant ??= $__data[\'variant\'] ?? $__defaults[\'variant\'];
unset($__data[\'variant\']);
unset($__defaults);
$attributes = \Livewire\Blaze\Runtime\BlazeAttributeBag::sanitized($__data, $__bound);
unset($__data, $__bound); ?><?php $__resolved = $__blaze->resolve(\'flux::\' . \'child.\' . $variant); ?>
<?php require_once $__blaze->compiledPath . \'/\' . $__resolved . \'.php\'; ?>
<?php $slots' . $slotsHash . ' = []; ?>
<?php ob_start(); ?>Hello World<?php $slots' . $slotsHash . '[\'slot\'] = new \Illuminate\View\ComponentSlot(trim(ob_get_clean()), []); ?>
<?php $slots' . $slotsHash . ' = array_merge($__blaze->mergedComponentSlots(), $slots' . $slotsHash . '); ?>
<?php (\'_\' . $__resolved)($__blaze, $attributes->all(), $slots' . $slotsHash . ', []); ?>
<?php unset($__resolved) ?>

<?php } endif; ?><?php /**PATH ' . $path . ' ENDPATH**/ ?>'
        );
    });

    it('compiles self-closing delegate-component', function () {
        $result = app('blaze')->compile('@blaze
<flux:delegate-component :component="$type" />');

        expect($result)->toBe(
            '@blaze
<?php $__resolved = $__blaze->resolve(\'flux::\' . $type); ?>
<?php require_once $__blaze->compiledPath . \'/\' . $__resolved . \'.php\'; ?>
<?php (\'_\' . $__resolved)($__blaze, $attributes->all(), $__blaze->mergedComponentSlots(), []); ?>
<?php unset($__resolved) ?>
'
        );
    });

    it('compiles delegate-component with named slots', function () {
        $path = __DIR__ . '/fixtures/delegate/delegate-with-slot.blade.php';
        $hash = Utils::hash($path);
        $compiled = compile('delegate/delegate-with-slot.blade.php');

        // Should contain the resolve call
        expect($compiled)->toContain('$__resolved = $__blaze->resolve(\'flux::\' . \'button.\' . $type)');
        // Should contain require_once with dynamic path
        expect($compiled)->toContain('require_once $__blaze->compiledPath . \'/\' . $__resolved . \'.php\'');
        // Should contain slot initialization
        expect($compiled)->toContain('$slots');
        expect($compiled)->toContain('= [];');
        // Should contain named slot compilation
        expect($compiled)->toContain('[\'icon\'] = new \Illuminate\View\ComponentSlot');
        // Should contain default slot compilation
        expect($compiled)->toContain('[\'slot\'] = new \Illuminate\View\ComponentSlot');
        // Should merge with parent slots
        expect($compiled)->toContain('array_merge($__blaze->mergedComponentSlots()');
        // Should contain dynamic function call
        expect($compiled)->toContain('(\'_\' . $__resolved)($__blaze, $attributes->all()');
        // Should clean up resolved variable
        expect($compiled)->toContain('unset($__resolved)');
    });

    it('does not compile regular flux components as delegate', function () {
        $result = app('blaze')->compile('@blaze
<flux:button>Click</flux:button>');

        expect($result)->not->toContain('$__blaze->resolve(');
        expect($result)->not->toContain('$__resolved');
    });
});
