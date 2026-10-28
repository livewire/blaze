<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\Component;
use Livewire\Blaze\Blaze;
use Livewire\Blaze\Events\ComponentFolded;
use Livewire\Blaze\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

expect()->extend('toEqualCollapsingWhitespace', function ($other) {
    expect(preg_replace('/\s+/', ' ', rtrim($this->value)))->toBe(preg_replace('/\s+/', ' ', rtrim($other)));

    return $this;
});

function fixture_path(string $filename): string
{
    return __DIR__ . '/fixtures/' . $filename;
}

function compare(string $input, array $data = []): void
{
    View::share('errors', new ViewErrorBag);

    $blaze = Blade::render($input, $data);

    Blaze::disable();
    Artisan::call('view:clear');
    Component::flushCache();

    $blade = Blade::render($input, $data);

    expect($blade)->toBe($blaze);
}

/**
 * Compares a template like compare() and checks the given components were folded, or none when empty
 */
function compareFolded(string $input, array $data, array $components): void
{
    $folded = [];

    Event::listen(ComponentFolded::class, function (ComponentFolded $event) use (&$folded) {
        $folded[] = $event->name;
    });

    compare($input, $data);

    $components ? expect($folded)->toContain(...$components) : expect($folded)->toBe([]);
}

dataset('unsafe value', ['"><img src=x onerror=alert(1)> a"b<c>&d\'e']);
