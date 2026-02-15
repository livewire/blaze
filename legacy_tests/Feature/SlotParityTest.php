<?php

/**
 * Slot Parity Tests
 *
 * These tests verify that Blaze-rendered components produce the same output
 * as Laravel Blade for all slot-related scenarios. Expected values are hardcoded
 * based on Laravel's actual behavior.
 */

beforeEach(function () {
    app('blade.compiler')->anonymousComponentPath(__DIR__ . '/fixtures');
});

describe('default slot', function () {
    it('renders loose content as default slot', function () {
        $result = blade(
            components: [
                'wrapper' => <<<'BLADE'
                    @blaze(fold: true)
                    <div>{{ $slot }}</div>
                    BLADE,
            ],
            view: <<<'BLADE'
                <x-wrapper>Hello World</x-wrapper>
                BLADE,
        );

        expect($result)->toBe(<<<'HTML'
            <div>Hello World</div>
            HTML
        );
    });

    it('renders loose content with whitespace trimmed', function () {
        $result = blade(
            components: [
                'wrapper' => <<<'BLADE'
                    @blaze(fold: true)
                    <div>{{ $slot }}</div>
                    BLADE,
            ],
            view: <<<'BLADE'
                <x-wrapper>
                    Hello World
                </x-wrapper>
                BLADE,
        );

        expect($result)->toBe(<<<'HTML'
            <div>Hello World</div>
            HTML
        );
    });

    it('renders explicit default slot with short syntax', function () {
        $result = blade(
            components: [
                'wrapper' => <<<'BLADE'
                    @blaze(fold: true)
                    <div>{{ $slot }}</div>
                    BLADE,
            ],
            view: <<<'BLADE'
                <x-wrapper><x-slot:slot>Explicit Content</x-slot></x-wrapper>
                BLADE,
        );

        expect($result)->toBe(<<<'HTML'
            <div>Explicit Content</div>
            HTML
        );
    });

    it('renders explicit default slot with name attribute', function () {
        $result = blade(
            components: [
                'wrapper' => <<<'BLADE'
                    @blaze(fold: true)
                    <div>{{ $slot }}</div>
                    BLADE,
            ],
            view: <<<'BLADE'
                <x-wrapper><x-slot name="slot">Explicit Content</x-slot></x-wrapper>
                BLADE,
        );

        expect($result)->toBe(<<<'HTML'
            <div>Explicit Content</div>
            HTML
        );
    });

    it('explicit default slot takes precedence over loose content', function () {
        $result = blade(
            components: [
                'wrapper' => <<<'BLADE'
                    @blaze(fold: true)
                    <div>{{ $slot }}</div>
                    BLADE,
            ],
            view: <<<'BLADE'
                <x-wrapper>
                    Loose Content
                    <x-slot:slot>Explicit Content</x-slot>
                </x-wrapper>
                BLADE,
        );

        expect($result)->toBe(<<<'HTML'
            <div>Explicit Content</div>
            HTML
        );
    });

    it('explicit default slot at beginning takes precedence', function () {
        $result = blade(
            components: [
                'wrapper' => <<<'BLADE'
                    @blaze(fold: true)
                    <div>{{ $slot }}</div>
                    BLADE,
            ],
            view: <<<'BLADE'
                <x-wrapper><x-slot:slot>Explicit</x-slot> Loose</x-wrapper>
                BLADE,
        );

        expect($result)->toBe(<<<'HTML'
            <div>Explicit</div>
            HTML
        );
    });
});

describe('named slots', function () {
    it('renders single named slot', function () {
        $result = blade(
            components: [
                'card' => <<<'BLADE'
                    <div class="card">
                        <div class="header">{{ $header }}</div>
                    </div>
                    BLADE,
            ],
            view: <<<'BLADE'
                <x-card><x-slot:header>Card Title</x-slot></x-card>
                BLADE,
        );

        expect($result)->toBe(<<<'HTML'
            <div class="card">
                <div class="header">Card Title</div>
            </div>
            HTML
        );
    });

    it('renders multiple named slots', function () {
        $result = blade(
            components: [
                'card' => <<<'BLADE'
                    <div class="card">
                        <div class="header">{{ $header }}</div>
                        <div class="footer">{{ $footer }}</div>
                    </div>
                    BLADE,
            ],
            view: <<<'BLADE'
                <x-card>
                    <x-slot:header>Title</x-slot>
                    <x-slot:footer>Footer</x-slot>
                </x-card>
                BLADE,
        );

        expect($result)->toBe(<<<'HTML'
            <div class="card">
                <div class="header">Title</div>
                <div class="footer">Footer</div>
            </div>
            HTML
        );
    });

    it('renders named slot with standard syntax', function () {
        $result = blade(
            components: [
                'card' => <<<'BLADE'
                    <div>{{ $header }}</div>
                    BLADE,
            ],
            view: <<<'BLADE'
                <x-card><x-slot name="header">Title</x-slot></x-card>
                BLADE,
        );

        expect($result)->toBe(<<<'HTML'
            <div>Title</div>
            HTML
        );
    });

    it('converts kebab-case slot names to camelCase', function () {
        $result = blade(
            components: [
                'card' => <<<'BLADE'
                    <div>{{ $cardHeader }}</div>
                    BLADE,
            ],
            view: <<<'BLADE'
                <x-card><x-slot:card-header>Title</x-slot></x-card>
                BLADE,
        );

        expect($result)->toBe(<<<'HTML'
            <div>Title</div>
            HTML
        );
    });
});

describe('named slots with default slot', function () {
    it('renders named slot and loose content together', function () {
        $result = blade(
            components: [
                'card' => <<<'BLADE'
                    <div class="card">
                        <div class="header">{{ $header }}</div>
                        <div class="body">{{ $slot }}</div>
                    </div>
                    BLADE,
            ],
            view: <<<'BLADE'
                <x-card>
                    <x-slot:header>Title</x-slot>
                    Body Content
                </x-card>
                BLADE,
        );

        expect($result)->toBe(<<<'HTML'
            <div class="card">
                <div class="header">Title</div>
                <div class="body">Body Content</div>
            </div>
            HTML
        );
    });

    it('renders loose content before and after named slot', function () {
        $result = blade(
            components: [
                'wrapper' => <<<'BLADE'
                    @blaze(fold: true)
                    <div>{{ $slot }}|{{ $middle }}</div>
                    BLADE,
            ],
            view: <<<'BLADE'
                <x-wrapper>
                    Before
                    <x-slot name="middle">Middle</x-slot>
                    After
                </x-wrapper>
                BLADE,
        );

        // Loose content is concatenated; Laravel adds a space where slot was
        expect($result)->toBe(<<<'HTML'
            <div>Before
                
                After|Middle</div>
            HTML
        );
    });

    it('renders multiple named slots with loose content between', function () {
        $result = blade(
            components: [
                'layout' => <<<'BLADE'
                    <header>{{ $header }}</header>
                    <main>{{ $slot }}</main>
                    <footer>{{ $footer }}</footer>
                    BLADE,
            ],
            view: <<<'BLADE'
                <x-layout>
                    <x-slot:header>Header</x-slot>
                    Main Content
                    <x-slot:footer>Footer</x-slot>
                </x-layout>
                BLADE,
        );

        expect($result)->toBe(<<<'HTML'
            <header>Header</header>
            <main>Main Content</main>
            <footer>Footer</footer>
            HTML
        );
    });

    it('handles loose content scattered around multiple named slots', function () {
        $result = blade(
            components: [
                'wrapper' => <<<'BLADE'
                    @blaze(fold: true)
                    <div>{{ $slot }}|{{ $a }}|{{ $b }}</div>
                    BLADE,
            ],
            view: <<<'BLADE'
                <x-wrapper>
                    Start
                    <x-slot:a>A</x-slot>
                    Middle
                    <x-slot:b>B</x-slot>
                    End
                </x-wrapper>
                BLADE,
        );

        // Laravel adds spaces where slot tags were removed
        expect($result)->toBe(<<<'HTML'
            <div>Start
                
                Middle
                
                End|A|B</div>
            HTML
        );
    });
});

describe('empty and missing slots', function () {
    it('renders default for missing optional slot', function () {
        $result = blade(
            components: [
                'card' => <<<'BLADE'
                    <div>{{ $header ?? "default" }}</div>
                    BLADE,
            ],
            view: <<<'BLADE'
                <x-card></x-card>
                BLADE,
        );

        expect($result)->toBe(<<<'HTML'
            <div>default</div>
            HTML
        );
    });

    it('renders empty slot content', function () {
        $result = blade(
            components: [
                'card' => <<<'BLADE'
                    <div>[{{ $header }}]</div>
                    BLADE,
            ],
            view: <<<'BLADE'
                <x-card><x-slot:header></x-slot></x-card>
                BLADE,
        );

        expect($result)->toBe(<<<'HTML'
            <div>[]</div>
            HTML
        );
    });

    it('renders empty named slot with standard syntax', function () {
        $result = blade(
            components: [
                'card' => <<<'BLADE'
                    <div>[{{ $header ?? "none" }}]</div>
                    BLADE,
            ],
            view: <<<'BLADE'
                <x-card><x-slot name="header"></x-slot></x-card>
                BLADE,
        );

        expect($result)->toBe(<<<'HTML'
            <div>[]</div>
            HTML
        );
    });

    it('self-closing component renders empty slot', function () {
        $result = blade(
            components: [
                'icon' => <<<'BLADE'
                    <i>{{ $slot }}</i>
                    BLADE,
            ],
            view: <<<'BLADE'
                <x-icon />
                BLADE,
        );

        // Laravel gives empty ComponentSlot for self-closing, not null
        expect($result)->toBe(<<<'HTML'
            <i></i>
            HTML
        );
    });
});

describe('slot with dynamic content', function () {
    it('renders blade expressions in slot', function () {
        $result = blade(
            components: [
                'wrapper' => <<<'BLADE'
                    @blaze(fold: true)
                    <div>{{ $slot }}</div>
                    BLADE,
            ],
            view: <<<'BLADE'
                <x-wrapper>{{ $name }}</x-wrapper>
                BLADE,
            data: ['name' => 'John'],
        );

        expect($result)->toBe(<<<'HTML'
            <div>John</div>
            HTML
        );
    });

    it('renders blade expressions in named slot', function () {
        $result = blade(
            components: [
                'card' => <<<'BLADE'
                    <div>{{ $header }}</div>
                    BLADE,
            ],
            view: <<<'BLADE'
                <x-card><x-slot:header>Hello {{ $name }}</x-slot></x-card>
                BLADE,
            data: ['name' => 'World'],
        );

        expect($result)->toBe(<<<'HTML'
            <div>Hello World</div>
            HTML
        );
    });

    it('renders nested component in slot', function () {
        $result = blade(
            components: [
                'outer' => <<<'BLADE'
                    <div class="outer">{{ $slot }}</div>
                    BLADE,
                'inner' => <<<'BLADE'
                    <span class="inner">{{ $slot }}</span>
                    BLADE,
            ],
            view: <<<'BLADE'
                <x-outer><x-inner>Nested</x-inner></x-outer>
                BLADE,
        );

        expect($result)->toBe(<<<'HTML'
            <div class="outer"><span class="inner">Nested</span></div>
            HTML
        );
    });
});

describe('slot attributes', function () {
    it('passes attributes to slot', function () {
        $result = blade(
            components: [
                'card' => <<<'BLADE'
                    <div class="{{ $header->attributes->get("class") }}">{{ $header }}</div>
                    BLADE,
            ],
            view: <<<'BLADE'
                <x-card><x-slot:header class="text-lg">Title</x-slot></x-card>
                BLADE,
        );

        expect($result)->toBe(<<<'HTML'
            <div class="text-lg">Title</div>
            HTML
        );
    });
});

describe('slot ordering', function () {
    it('renders slots regardless of definition order', function () {
        $result = blade(
            components: [
                'card' => <<<'BLADE'
                    <div class="header">{{ $header }}</div>
                    <div class="body">{{ $slot }}</div>
                    <div class="footer">{{ $footer }}</div>
                    BLADE,
            ],
            view: <<<'BLADE'
                <x-card>
                    <x-slot:footer>Footer First</x-slot>
                    Body Content
                    <x-slot:header>Header Last</x-slot>
                </x-card>
                BLADE,
        );

        expect($result)->toBe(<<<'HTML'
            <div class="header">Header Last</div>
            <div class="body">Body Content</div>
            <div class="footer">Footer First</div>
            HTML
        );
    });

    it('renders only named slots without loose content', function () {
        $result = blade(
            components: [
                'card' => <<<'BLADE'
                    <div class="header">{{ $header }}</div>
                    <div class="body">{{ $slot }}</div>
                    BLADE,
            ],
            view: <<<'BLADE'
                <x-card>
                    <x-slot:header>Header Only</x-slot>
                </x-card>
                BLADE,
        );

        expect($result)->toBe(<<<'HTML'
            <div class="header">Header Only</div>
            <div class="body"></div>
            HTML
        );
    });

    it('handles whitespace-only loose content', function () {
        $result = blade(
            components: [
                'card' => <<<'BLADE'
                    <div>[{{ $slot }}]</div>
                    BLADE,
            ],
            view: <<<'BLADE'
                <x-card>
                    <x-slot:header>Header</x-slot>
                </x-card>
                BLADE,
        );

        // Whitespace around named slot becomes empty after trim
        expect($result)->toBe(<<<'HTML'
            <div>[]</div>
            HTML
        );
    });
});

describe('nested components in slots', function () {
    it('renders deeply nested components', function () {
        $result = blade(
            components: [
                'outer' => <<<'BLADE'
                    <div class="outer">{{ $slot }}</div>
                    BLADE,
                'middle' => <<<'BLADE'
                    <div class="middle">{{ $slot }}</div>
                    BLADE,
                'inner' => <<<'BLADE'
                    <div class="inner">{{ $slot }}</div>
                    BLADE,
            ],
            view: <<<'BLADE'
                <x-outer>
                    <x-middle>
                        <x-inner>Deep Content</x-inner>
                    </x-middle>
                </x-outer>
                BLADE,
        );

        expect($result)->toBe(<<<'HTML'
            <div class="outer"><div class="middle"><div class="inner">Deep Content</div></div></div>
            HTML
        );
    });

    it('renders component in named slot', function () {
        $result = blade(
            components: [
                'card' => <<<'BLADE'
                    <div class="header">{{ $header }}</div>
                    <div class="body">{{ $slot }}</div>
                    BLADE,
                'badge' => <<<'BLADE'
                    <span class="badge">{{ $slot }}</span>
                    BLADE,
            ],
            view: <<<'BLADE'
                <x-card>
                    <x-slot:header><x-badge>New</x-badge> Title</x-slot>
                    Body
                </x-card>
                BLADE,
        );

        expect($result)->toBe(<<<'HTML'
            <div class="header"><span class="badge">New</span> Title</div>
            <div class="body">Body</div>
            HTML
        );
    });

    it('renders multiple components in default slot', function () {
        $result = blade(
            components: [
                'wrapper' => <<<'BLADE'
                    @blaze(fold: true)
                    <div>{{ $slot }}</div>
                    BLADE,
                'item' => <<<'BLADE'
                    <span>{{ $slot }}</span>
                    BLADE,
            ],
            view: <<<'BLADE'
                <x-wrapper><x-item>A</x-item><x-item>B</x-item><x-item>C</x-item></x-wrapper>
                BLADE,
        );

        expect($result)->toBe(<<<'HTML'
            <div><span>A</span><span>B</span><span>C</span></div>
            HTML
        );
    });
});

describe('whitespace handling', function () {
    it('trims slot content', function () {
        $result = blade(
            components: [
                'wrapper' => <<<'BLADE'
                    @blaze(fold: true)
                    <div>[{{ $slot }}]</div>
                    BLADE,
            ],
            view: <<<'BLADE'
                <x-wrapper>   spaced   </x-wrapper>
                BLADE,
        );

        expect($result)->toBe(<<<'HTML'
            <div>[spaced]</div>
            HTML
        );
    });

    it('preserves internal whitespace in slot', function () {
        $result = blade(
            components: [
                'wrapper' => <<<'BLADE'
                    @blaze(fold: true)
                    <div>{{ $slot }}</div>
                    BLADE,
            ],
            view: <<<'BLADE'
                <x-wrapper>hello   world</x-wrapper>
                BLADE,
        );

        expect($result)->toBe(<<<'HTML'
            <div>hello   world</div>
            HTML
        );
    });

    it('preserves newlines within slot content', function () {
        $result = blade(
            components: [
                'wrapper' => <<<'BLADE'
                    @blaze(fold: true)
                    <pre>{{ $slot }}</pre>
                    BLADE,
            ],
            view: <<<'BLADE'
                <x-wrapper>line1
                line2</x-wrapper>
                BLADE,
        );

        expect($result)->toBe(<<<'HTML'
            <pre>line1
            line2</pre>
            HTML
        );
    });
});
