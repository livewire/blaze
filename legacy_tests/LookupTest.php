<?php

use Livewire\Blaze\BladeService;

describe('lookup component paths', function () {
    beforeEach(function () {
        app('blade.compiler')->anonymousComponentPath(__DIR__ . '/fixtures/components');
        app('blade.compiler')->anonymousComponentPath(__DIR__ . '/fixtures/pages', 'pages');
    });

    it('anonymous component', function () {
        $path = BladeService::componentNameToPath('button');

        expect($path)->toBe(__DIR__ . '/fixtures/components/button.blade.php');
    });

    it('namespaced anonymous component', function () {
        $path = BladeService::componentNameToPath('pages::dashboard');

        expect($path)->toBe(__DIR__ . '/fixtures/pages/dashboard.blade.php');
    });

    it('sub-component', function () {
        $path = BladeService::componentNameToPath('form.input');

        expect($path)->toBe(__DIR__ . '/fixtures/components/form/input.blade.php');
    });

    it('nested sub-component', function () {
        $path = BladeService::componentNameToPath('form.fields.text');

        expect($path)->toBe(__DIR__ . '/fixtures/components/form/fields/text.blade.php');
    });

    it('root component with index file', function () {
        $path = BladeService::componentNameToPath('form');

        expect($path)->toBe(__DIR__ . '/fixtures/components/form/index.blade.php');
    });

    it('root component with same-name file', function () {
        $path = BladeService::componentNameToPath('panel');

        expect($path)->toBe(__DIR__ . '/fixtures/components/panel/panel.blade.php');
    });

    it('namespaced sub-component', function () {
        $path = BladeService::componentNameToPath('pages::auth.login');

        expect($path)->toBe(__DIR__ . '/fixtures/pages/auth/login.blade.php');
    });

    it('namespaced root component', function () {
        $path = BladeService::componentNameToPath('pages::auth');

        expect($path)->toBe(__DIR__ . '/fixtures/pages/auth/index.blade.php');
    });
});
