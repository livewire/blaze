<?php

use Livewire\Blaze\Support\Utils;

/*
|--------------------------------------------------------------------------
| Call-site compilation
|--------------------------------------------------------------------------
| Tests that component usage sites compile to the correct ensureCompiled →
| require_once → pushData → function call → popData sequence.
*/

test('call-site compiles blaze component with static attributes', function () {
    $path = fixture_path('components/simple-button.blade.php');
    $hash = Utils::hash($path);

    $result = app('blade.compiler')->compileString('<x-simple-button type="submit" class="btn-primary" />');

    expect($result)
        ->toContain("\$__blaze->ensureCompiled('{$path}'")
        ->toContain("require_once \$__blaze->compiledPath.'/{$hash}.php'")
        ->toContain("'type' => 'submit','class' => 'btn-primary'")
        ->toContain("_{$hash}(\$__blaze,")
        ->toContain('$__blaze->popData()');
});

test('call-site compiles dynamic attributes with bound array', function ($template, $expectedValues) {
    $result = app('blade.compiler')->compileString($template);

    expect($result)
        ->toContain($expectedValues)
        ->toContain("['type', 'class']");
})->with([
    'explicit binding' => ['<x-simple-button :type="$buttonType" :class="$classes" />', "'type' => \$buttonType,'class' => \$classes"],
    'shorthand'        => ['<x-simple-button :$type :$class />', "'type' => \$type,'class' => \$class"],
]);

test('call-site compiles boolean attributes as true', function () {
    $result = app('blade.compiler')->compileString('<x-simple-button disabled required />');

    expect($result)
        ->toContain("'disabled' => true")
        ->toContain("'required' => true");
});

test('call-site preserves wire and alpine attributes', function () {
    $result = app('blade.compiler')->compileString('<x-simple-button wire:click="save" x-on:click="open = true" @click="handle" />');

    expect($result)
        ->toContain("'wire:click' => 'save'")
        ->toContain("'x-on:click' => 'open = true'")
        ->toContain("'@click' => 'handle'");
});

test('non-blaze components fall back to Laravel', function () {
    $result = app('blade.compiler')->compileString('<x-non-blaze-button type="submit" />');

    expect($result)
        ->not->toContain('$__blaze->ensureCompiled')
        ->toContain('Illuminate\View\AnonymousComponent');
});

test('compiled output ends with trailing newline', function () {
    $result = app('blade.compiler')->compileString('<x-simple-button />');

    expect($result)->toEndWith("?>\n");
});

test('pushData comes before function call and popData comes after', function () {
    $path = fixture_path('components/aware-menu.blade.php');
    $hash = Utils::hash($path);

    $result = app('blade.compiler')->compileString('<x-aware-menu color="blue">Content</x-aware-menu>');

    $pushPos = strpos($result, 'pushData');
    $callPos = strpos($result, '_' . $hash . '($__blaze');
    $popPos = strpos($result, 'popData');

    expect($pushPos)->toBeLessThan($callPos);
    expect($callPos)->toBeLessThan($popPos);
});

/*
|--------------------------------------------------------------------------
| Slot compilation
|--------------------------------------------------------------------------
*/

test('default slot compiles with ob_start and ComponentSlot', function () {
    $result = app('blade.compiler')->compileString('<x-card-header>Hello World</x-card-header>');

    expect($result)
        ->toContain('ob_start()')
        ->toContain("['slot'] = new \\Illuminate\\View\\ComponentSlot(trim(ob_get_clean()), [])");
});

test('named slot compiles with short syntax', function () {
    $result = app('blade.compiler')->compileString('<x-card-header><x-slot:header>Header</x-slot:header>Body</x-card-header>');

    expect($result)
        ->toContain("['header'] = new \\Illuminate\\View\\ComponentSlot(trim(ob_get_clean())")
        ->toContain("['slot'] = new \\Illuminate\\View\\ComponentSlot(trim(ob_get_clean()), [])");
});

test('kebab-case slot names compile to camelCase', function () {
    $result = app('blade.compiler')->compileString('<x-card-header><x-slot:card-header>Header</x-slot:card-header></x-card-header>');

    expect($result)->toContain("['cardHeader'] = new \\Illuminate\\View\\ComponentSlot");
});

test('self-closing component compiles without slot handling', function () {
    $result = app('blade.compiler')->compileString('<x-simple-button />');

    expect($result)
        ->not->toContain('$slot')
        ->not->toContain('ob_start');
});

test('dynamic slot names fall back to Laravel', function () {
    $result = app('blade.compiler')->compileString('<x-card-header><x-slot :name="$slotName">Content</x-slot></x-card-header>');

    expect($result)
        ->not->toContain('$__blaze->ensureCompiled')
        ->toContain('Illuminate\View\AnonymousComponent');
})->skip();

/*
|--------------------------------------------------------------------------
| Sanitization placement
|--------------------------------------------------------------------------
*/

test('component call-site does not include sanitizeComponentAttribute', function () {
    $result = app('blade.compiler')->compileString('<x-simple-button :type="$type" />');

    expect($result)
        ->not->toContain('sanitizeComponentAttribute')
        ->toContain("'type' => \$type");
});

test('slot attributes include sanitizeComponentAttribute', function () {
    $result = app('blade.compiler')->compileString('<x-card-with-attributes><x-slot:header :class="$classes">Header</x-slot:header>Body</x-card-with-attributes>');

    expect($result)->toContain('sanitizeComponentAttribute');
});

/*
|--------------------------------------------------------------------------
| Component wrapper compilation (the function that wraps the template)
|--------------------------------------------------------------------------
*/

test('wrapper generates function with correct signature', function () {
    $path = fixture_path('components/simple-button.blade.php');
    $hash = Utils::hash($path);
    $compiled = compile('simple-button.blade.php');

    expect($compiled)
        ->toContain("function _{$hash}(\$__blaze, \$__data = [], \$__slots = [], \$__bound = [], \$__this = null)")
        ->toContain('$__env = $__blaze->env')
        ->toContain('extract($__slots, EXTR_SKIP)')
        ->toContain('unset($__slots)')
        ->toContain('BlazeAttributeBag::sanitized($__data, $__bound)')
        ->toContain("/**PATH {$path} ENDPATH**/");
});

// @aware lookup, @props defaults/cleanup, required props, and kebab-case unset
// are covered by AwareCompilerTest and PropsCompilerTest at the compiler level.

test('wrapper creates early $attributes when referenced in @props defaults', function () {
    $compiled = compile('props-attrs-ref.blade.php');

    $earlyPos = strpos($compiled, '$attributes = new \Livewire\Blaze\Runtime\BlazeAttributeBag($__data)');
    $defaultsPos = strpos($compiled, '$__defaults = ');

    expect($earlyPos)->not->toBeFalse();
    expect($earlyPos)->toBeLessThan($defaultsPos);
});

test('wrapper creates both early and sanitized $attributes when used in @props and template', function () {
    $compiled = compile('props-attrs-both.blade.php');

    $earlyPos = strpos($compiled, '$attributes = new \Livewire\Blaze\Runtime\BlazeAttributeBag($__data)');
    $sanitizedPos = strpos($compiled, 'BlazeAttributeBag::sanitized($__data, $__bound)');

    expect($earlyPos)->not->toBeFalse();
    expect($sanitizedPos)->not->toBeFalse();
    expect($earlyPos)->toBeLessThan($sanitizedPos);
});

test('wrapper detects $attributes usage inside @php blocks', function () {
    $compiled = compile('attrs-in-php-block.blade.php');

    expect($compiled)->toContain('BlazeAttributeBag::sanitized($__data, $__bound)');
});

test('wrapper uses extract when no @props directive', function () {
    $compiled = compile('no-props.blade.php');

    expect($compiled)
        ->toContain('extract($__data, EXTR_SKIP)')
        ->not->toContain('BlazeAttributeBag::sanitized');
});

test('wrapper does not use extract when @props is present', function () {
    $compiled = compile('props-simple.blade.php');

    expect($compiled)
        ->not->toContain('extract($__data, EXTR_SKIP)')
        ->toContain("\$foo ??= \$__data['foo']");
});

// 'wrapper uses sanitized factory method' is already covered by
// the assertion in 'wrapper generates function with correct signature'.
