<?php

use Livewire\Blaze\Config;

it('does not trigger deprecation when no configured path matches', function (string $method) {
    $config = app(Config::class)->clear();

    $config->add(fixture_path('does-not-exist'), compile: true, memo: true, fold: true);

    set_error_handler(function (int $severity, string $message): bool {
        if ($severity === E_DEPRECATED) {
            throw new \ErrorException($message, 0, $severity);
        }

        return false;
    });

    try {
        expect($config->{$method}(fixture_path('components/input.blade.php')))->toBeFalse();
    } finally {
        restore_error_handler();
    }
})->with([
    'compile' => 'shouldCompile',
    'memoize' => 'shouldMemoize',
    'fold' => 'shouldFold',
]);
