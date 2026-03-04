<?php

use Illuminate\Container\Container;
use Illuminate\Contracts\View\Engine;
use Illuminate\Support\Facades\Artisan;
use Livewire\Blaze\Blaze;
use Livewire\Blaze\BlazeManager;

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
