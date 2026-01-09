<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    app('blade.compiler')->anonymousComponentPath(__DIR__ . '/fixtures');
});

describe('props sanitization', function () {
    it('props with ampersand are escaped once not double-escaped', function () {
        $result = blade(
            components: [
                'button' => <<<'BLADE'
                    @blaze
                    @props(['label' => 'Default'])
                    <button>{{ $label }}</button>
                    BLADE
                ,
            ],
            view: '<x-button :label="$text" />',
            data: ['text' => 'Save & Continue'],
        );

        expect($result)->toContain('<button>Save &amp; Continue</button>');
        expect($result)->not->toContain('&amp;amp;');
    });

    it('props with raw user input are properly escaped once', function () {
        $dangerousInput = '<script>alert("xss")</script>';
        $result = blade(
            components: [
                'button' => <<<'BLADE'
                    @blaze
                    @props(['label' => 'Default'])
                    <button>{{ $label }}</button>
                    BLADE
                ,
            ],
            view: '<x-button :label="$input" />',
            data: ['input' => $dangerousInput],
        );

        expect($result)->toContain('&lt;script&gt;');
        expect($result)->toContain('&lt;/script&gt;');
        expect($result)->not->toContain('&amp;lt;');
    });

    it('props passed from variables are not double-escaped', function () {
        $result = blade(
            components: [
                'label' => <<<'BLADE'
                    @blaze
                    @props(['label'])
                    <span>{{ $label }}</span>
                    BLADE
                ,
            ],
            view: '<x-label :label="$text" />',
            data: ['text' => 'Tom & Jerry'],
        );

        expect($result)->toContain('<span>Tom &amp; Jerry</span>');
        expect($result)->not->toContain('&amp;amp;');
    });
});

describe('attributes sanitization', function () {
    it('attributes with dangerous values are sanitized', function () {
        $dangerousInput = '<script>alert("xss")</script>';
        $result = blade(
            components: [
                'simple-button' => <<<'BLADE'
                    @blaze
                    <button {{ $attributes }}>Click</button>
                    BLADE
                ,
            ],
            view: '<x-simple-button :data-value="$input" />',
            data: ['input' => $dangerousInput],
        );

        expect($result)->toContain('&lt;script&gt;');
        expect($result)->toContain('&lt;/script&gt;');
    });

    it('attributes with ampersand are sanitized', function () {
        $result = blade(
            components: [
                'simple-button' => <<<'BLADE'
                    @blaze
                    <button {{ $attributes }}>Click</button>
                    BLADE
                ,
            ],
            view: '<x-simple-button :data-value="$text" />',
            data: ['text' => 'Tom & Jerry'],
        );

        expect($result)->toContain('data-value="Tom &amp; Jerry"');
    });

    it('static attributes preserve original format', function () {
        $result = blade(
            components: [
                'simple-button' => <<<'BLADE'
                    @blaze
                    <button {{ $attributes }}>Click</button>
                    BLADE
                ,
            ],
            view: '<x-simple-button data-value="safe-value" />',
        );

        expect($result)->toContain('data-value="safe-value"');
    });
});

describe('slot attributes sanitization', function () {
    it('slot attributes with dangerous values are sanitized', function () {
        $dangerousInput = '<script>alert("xss")</script>';
        $result = blade(
            components: [
                'card' => <<<'BLADE'
                    @blaze
                    <div class="card">
                        <div {{ $header->attributes }}>{{ $header }}</div>
                        <div class="card-body">{{ $slot }}</div>
                    </div>
                    BLADE
                ,
            ],
            view: '<x-card><x-slot:header :data-test="$input">Header</x-slot:header>Body</x-card>',
            data: ['input' => $dangerousInput],
        );

        expect($result)->toContain('&lt;script&gt;');
    });
});

describe('aware sanitization', function () {
    it('aware values are passed raw and escaped once when rendered', function () {
        // Uses fixtures: aware-menu passes color to aware-menu-item via @aware
        $result = \Illuminate\Support\Facades\Blade::render(
            '<x-aware-menu :color="$color"><x-aware-menu-item>Item</x-aware-menu-item></x-aware-menu>',
            ['color' => 'Tom & Jerry'],
        );

        expect($result)->toContain('text-Tom &amp; Jerry-800');
        expect($result)->not->toContain('&amp;amp;');
    });
});

describe('stringable objects', function () {
    it('stringable objects are not double-escaped', function () {
        $result = blade(
            components: [
                'button' => <<<'BLADE'
                    @blaze
                    @props(['label'])
                    <button>{{ $label }}</button>
                    BLADE
                ,
            ],
            view: '<x-button :label="str(\'<b>Bold</b>\')" />',
        );

        expect($result)->toContain('&lt;b&gt;Bold&lt;/b&gt;');
        expect($result)->not->toContain('&amp;lt;');
    });
});
