<?php

use Illuminate\Contracts\View\Engine;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Livewire\Blaze\Blaze;

test('renders components', function () {
    Artisan::call('view:clear');
    
    view('inputs')->render();
})->throwsNoExceptions();

test('renders components with blaze off', function () {
    Artisan::call('view:clear');

    Blaze::disable();
    
    view('inputs')->render();
})->throwsNoExceptions();

test('renders components with blaze off and debug mode on', function () {
    Artisan::call('view:clear');

    Blaze::disable();
    Blaze::debug();
    
    view('inputs')->render();
})->throwsNoExceptions();

test('ignores verbatim blocks', function () {
    $input = '@verbatim<x-input />@endverbatim';

    expect(Blade::render($input))->toBe('<x-input />');
});

test('ignores php directives', function () {
    $input = "@php echo '<x-input />'; @endphp";

    expect(Blade::render($input))->toBe('<x-input />');
});

test('ignores comments', function () {
    $input = '{{-- <x-input /> --}}';

    expect(Blade::render($input))->toBe('');
});

test('supports php engine', function () {
    // Make sure our hooks do not break views
    // rendered using the regular php engine.
    view('php-view')->render();
})->throwsNoExceptions();

test('injects blaze runtime when blade engine is decorated', function () {
    Artisan::call('view:clear');

    $resolver = app('view.engine.resolver');
    $bladeEngine = $resolver->resolve('blade');

    $resolver->register('blade', function () use ($bladeEngine) {
        return new class ($bladeEngine) implements Engine
        {
            public function __construct(protected Engine $engine) {}

            public function get($path, array $data = [])
            {
                return $this->engine->get($path, $data);
            }

            public function getEngine(): Engine
            {
                return $this->engine;
            }
        };
    });

    view('inputs')->render();
})->throwsNoExceptions();
