<?php

use Livewire\Blaze\BlazeServiceProvider;

it('registers the service provider', function () {
    $providers = $this->app->getLoadedProviders();

    expect($providers)->toHaveKey(BlazeServiceProvider::class);
});

it('boots without errors', function () {
    expect(true)->toBeTrue();
});