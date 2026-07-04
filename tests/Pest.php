<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\Component;
use Livewire\Blaze\Blaze;
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