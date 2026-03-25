<?php

use Illuminate\Container\Container;
use Illuminate\Contracts\View\Engine;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Component;
use Livewire\Blaze\Blaze;
use Livewire\Blaze\BlazeManager;
use Livewire\Blaze\Runtime\BlazeRuntime;

beforeEach(fn () => Artisan::call('view:clear'));

test('renders components', function () {
    view('mix')->render();
})->throwsNoExceptions();

test('renders components with blaze off', function () {
    Blaze::disable();
    
    view('mix')->render();
})->throwsNoExceptions();

test('renders components with blaze off and debug mode on', function () {
    Blaze::disable();
    Blaze::debug();
    
    view('mix')->render();
})->throwsNoExceptions();

test('renders components after clearing compiled views in the same process', function () {
    view('mix')->render();

    Artisan::call('view:clear');

    view('mix')->render();
})->throwsNoExceptions();

test('supports php engine', function () {
    view('php-view')->render();
})->throwsNoExceptions();

test('supports decorated engine', function () {
    $resolver = app('view.engine.resolver');
    $blade = $resolver->resolve('blade');

    // This replicates how Sentry wraps the blade engine...
    $resolver->register('blade', function () use ($blade) {
        return new class($blade) implements Engine {
            public function __construct(private Engine $engine) {}

            public function get($path, array $data = []): string {
                return $this->engine->get($path, $data);
            }
        };
    });

    view('mix')->render();
})->throwsNoExceptions();

test('supports antlers engine', function () {
    // Statamic serializes all view data, we need to make sure
    // we don't inject BlazeRuntime which is not serializable.
    app('view')->addExtension('antlers.html', 'antlers', function () {
        return new class implements Engine {
            public function get($path, array $data = []): string {
                return isset($data['__blaze']) ? 'BLAZE' : 'NO_BLAZE';
            }
        };
    });

    expect(view('antlers-view')->render())->toBe('NO_BLAZE');
});

test('forwards $__this through component chain', function () {
    // Parent component does NOT use $this itself, but renders a child that DOES.
    // Without the fix, the parent passes null instead of forwarding $__this,
    // causing "Using $this when not in object context" in the child.

    $blaze = app(BlazeRuntime::class);

    // Compile both component functions via resolve().
    $blaze->resolve('this-child');
    $blaze->resolve('this-parent');

    $parentPath = fixture_path('views/components/this-parent.blade.php');
    $parentFn = '_' . \Livewire\Blaze\Support\Utils::hash($parentPath);

    // Call the parent function with a fake $__this.
    // The parent should forward it to the child, which uses $this->id.
    $fakeComponent = new class { public string $id = 'test-123'; };

    ob_start();
    $parentFn($blaze, [], [], [], $fakeComponent);
    $output = ob_get_clean();

    expect($output)->toContain('test-123');
});

test('folds and compiles the same component', function () {
    Blade::render(<<<'BLADE'
        <x-foldable.input required /> {{-- Folded --}}
        <x-foldable.input :required="$required" /> {{-- Compiled (fallback) --}}
        BLADE,
        ['required' => true]
    );
})->throwsNoExceptions();