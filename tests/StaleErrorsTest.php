<?php

use Illuminate\Support\ViewErrorBag;
use Illuminate\Support\MessageBag;
use Livewire\Blaze\Runtime\BlazeRuntime;

test('errors are fresh across requests in long-lived processes', function () {
    /** @var BlazeRuntime $runtime */
    $runtime = app(BlazeRuntime::class);

    // Request #1: page loads with no validation errors.
    app('view')->share('errors', new ViewErrorBag);

    expect($runtime->errors->any())->toBeFalse();

    // Request #2: form submission fails, middleware shares fresh errors.
    $freshErrors = new ViewErrorBag;
    $freshErrors->put('default', new MessageBag([
        'email' => ['These credentials do not match our records.'],
    ]));
    app('view')->share('errors', $freshErrors);

    // The singleton must return the fresh errors, not the stale empty bag.
    expect($runtime->errors->any())->toBeTrue();
});
