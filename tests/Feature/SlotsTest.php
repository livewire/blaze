<?php

beforeEach(function () {
    app('blade.compiler')->anonymousComponentPath(__DIR__ . '/fixtures');
});

describe('default slot', function () {
    it('renders default slot content', function () {
        $result = blade(
            components: [
                'card' => <<<'BLADE'
                    @blaze
                    <div class="card">
                        <div class="card-body">{{ $slot }}</div>
                    </div>
                    BLADE
                ,
            ],
            view: '<x-card>Hello World</x-card>',
        );

        expect($result)->toContain('<div class="card">');
        expect($result)->toContain('<div class="card-body">Hello World</div>');
    });

    it('renders empty when no content provided', function () {
        $result = blade(
            components: [
                'card' => <<<'BLADE'
                    @blaze
                    <div class="card">
                        <div class="card-body">{{ $slot }}</div>
                    </div>
                    BLADE
                ,
            ],
            view: '<x-card></x-card>',
        );

        expect($result)->toContain('<div class="card-body"></div>');
    });

    it('renders HTML in default slot', function () {
        $result = blade(
            components: [
                'card' => <<<'BLADE'
                    @blaze
                    <div class="card">
                        <div class="card-body">{{ $slot }}</div>
                    </div>
                    BLADE
                ,
            ],
            view: '<x-card><strong>Bold</strong> text</x-card>',
        );

        expect($result)->toContain('<div class="card-body"><strong>Bold</strong> text</div>');
    });

    it('renders Blade expressions in default slot', function () {
        $result = blade(
            components: [
                'card' => <<<'BLADE'
                    @blaze
                    <div class="card">
                        <div class="card-body">{{ $slot }}</div>
                    </div>
                    BLADE
                ,
            ],
            view: '<x-card>Count: {{ $count }}</x-card>',
            data: ['count' => 42],
        );

        expect($result)->toContain('<div class="card-body">Count: 42</div>');
    });
});

describe('named slots', function () {
    it('renders named slot with short syntax', function () {
        $result = blade(
            components: [
                'card-header' => <<<'BLADE'
                    @blaze
                    <div class="card">
                        <div class="card-header">{{ $header ?? '' }}</div>
                        <div class="card-body">{{ $slot }}</div>
                    </div>
                    BLADE
                ,
            ],
            view: '<x-card-header><x-slot:header>My Header</x-slot:header>Body content</x-card-header>',
        );

        expect($result)->toContain('<div class="card-header">My Header</div>');
        expect($result)->toContain('<div class="card-body">Body content</div>');
    });

    it('renders named slot with standard syntax', function () {
        $result = blade(
            components: [
                'card-header' => <<<'BLADE'
                    @blaze
                    <div class="card">
                        <div class="card-header">{{ $header ?? '' }}</div>
                        <div class="card-body">{{ $slot }}</div>
                    </div>
                    BLADE
                ,
            ],
            view: '<x-card-header><x-slot name="header">My Header</x-slot>Body content</x-card-header>',
        );

        expect($result)->toContain('<div class="card-header">My Header</div>');
        expect($result)->toContain('<div class="card-body">Body content</div>');
    });

    it('renders multiple named slots', function () {
        $result = blade(
            components: [
                'card-full' => <<<'BLADE'
                    @blaze
                    <div class="card">
                        @if(isset($header))
                        <div class="card-header">{{ $header }}</div>
                        @endif
                        <div class="card-body">{{ $slot }}</div>
                        @if(isset($footer))
                        <div class="card-footer">{{ $footer }}</div>
                        @endif
                    </div>
                    BLADE
                ,
            ],
            view: '<x-card-full><x-slot:header>Header</x-slot:header><x-slot:footer>Footer</x-slot:footer>Body</x-card-full>',
        );

        expect($result)->toContain('<div class="card-header">Header</div>');
        expect($result)->toContain('<div class="card-body">Body</div>');
        expect($result)->toContain('<div class="card-footer">Footer</div>');
    });

    it('handles missing optional named slots', function () {
        $result = blade(
            components: [
                'card-header' => <<<'BLADE'
                    @blaze
                    <div class="card">
                        <div class="card-header">{{ $header ?? '' }}</div>
                        <div class="card-body">{{ $slot }}</div>
                    </div>
                    BLADE
                ,
            ],
            view: '<x-card-header>Just body</x-card-header>',
        );

        expect($result)->toContain('<div class="card-header"></div>');
        expect($result)->toContain('<div class="card-body">Just body</div>');
    });

    it('converts kebab-case slot names to camelCase', function () {
        $result = blade(
            components: [
                'test' => <<<'BLADE'
                    @blaze
                    <div>{{ $cardHeader ?? "none" }}</div>
                    BLADE
                ,
            ],
            view: '<x-test><x-slot:card-header>Header</x-slot:card-header></x-test>',
        );

        expect($result)->toContain('<div>Header</div>');
    });

    it('handles empty named slot', function () {
        $result = blade(
            components: [
                'card-header' => <<<'BLADE'
                    @blaze
                    <div class="card">
                        <div class="card-header">{{ $header ?? '' }}</div>
                        <div class="card-body">{{ $slot }}</div>
                    </div>
                    BLADE
                ,
            ],
            view: '<x-card-header><x-slot:header></x-slot:header>Body</x-card-header>',
        );

        expect($result)->toContain('<div class="card-header"></div>');
    });

    it('x-slot without name defaults to slot', function () {
        $result = blade(
            components: [
                'test' => <<<'BLADE'
                    @blaze
                    <div>{{ $slot }}</div>
                    BLADE
                ,
            ],
            view: '<x-test><x-slot>Content</x-slot></x-test>',
        );

        expect($result)->toContain('<div>Content</div>');
    });

    it('explicit default slot takes precedence over loose content', function () {
        $template = <<<'BLADE'
            @blaze
            <div>{{ $slot }}</div>
            BLADE;

        $result = blade(
            components: ['test' => $template],
            view: '<x-test>Loose<x-slot:slot>Explicit</x-slot></x-test>',
        );
        expect($result)->toContain('<div>Explicit</div>');
        expect($result)->not->toContain('Loose');

        $result = blade(
            components: ['test' => $template],
            view: '<x-test><x-slot:slot>Explicit</x-slot>Loose</x-test>',
        );
        expect($result)->toContain('<div>Explicit</div>');
        expect($result)->not->toContain('Loose');

        $result = blade(
            components: ['test' => $template],
            view: '<x-test>Loose<x-slot name="slot">Explicit</x-slot></x-test>',
        );
        expect($result)->toContain('<div>Explicit</div>');
        expect($result)->not->toContain('Loose');

        $result = blade(
            components: ['test' => $template],
            view: '<x-test>Loose<x-slot>Explicit</x-slot></x-test>',
        );
        expect($result)->toContain('<div>Explicit</div>');
        expect($result)->not->toContain('Loose');
    });
});

describe('slot attributes', function () {
    it('passes static attributes to slot', function () {
        $result = blade(
            components: [
                'card-with-attributes' => <<<'BLADE'
                    @blaze
                    <div class="card">
                        @if(isset($header))
                        <div class="{{ $header->attributes->get('class', '') }}">{{ $header }}</div>
                        @endif
                        <div class="card-body">{{ $slot }}</div>
                    </div>
                    BLADE
                ,
            ],
            view: '<x-card-with-attributes><x-slot:header class="bold">Header</x-slot:header>Body</x-card-with-attributes>',
        );

        expect($result)->toContain('class="bold"');
    });

    it('passes dynamic attributes to slot', function () {
        $result = blade(
            components: [
                'card-with-attributes' => <<<'BLADE'
                    @blaze
                    <div class="card">
                        @if(isset($header))
                        <div class="{{ $header->attributes->get('class', '') }}">{{ $header }}</div>
                        @endif
                        <div class="card-body">{{ $slot }}</div>
                    </div>
                    BLADE
                ,
            ],
            view: '<x-card-with-attributes><x-slot:header :class="$headerClass">Header</x-slot:header>Body</x-card-with-attributes>',
            data: ['headerClass' => 'text-red'],
        );

        expect($result)->toContain('class="text-red"');
    });

    it('short syntax with name attribute treats name as slot attribute', function () {
        $result = blade(
            components: [
                'test' => <<<'BLADE'
                    @blaze
                    <input {{ $input->attributes }} value="{{ $input }}">
                    BLADE
                ,
            ],
            view: '<x-test><x-slot:input name="email">Value</x-slot:input></x-test>',
        );

        expect($result)->toContain('name="email"');
        expect($result)->toContain('value="Value"');
    });

    it('x-slot without name loses attributes (Laravel parity)', function () {
        $result = blade(
            components: [
                'test' => <<<'BLADE'
                    @blaze
                    <div data-slot-attr="{{ $slot->attributes->get('data-key', 'none') }}">{{ $slot }}</div>
                    BLADE
                ,
            ],
            view: '<x-test><x-slot data-key="1">Content</x-slot></x-test>',
        );

        expect($result)->toContain('data-slot-attr="none"');
    });

    it('x-slot name="slot" preserves attributes', function () {
        $result = blade(
            components: [
                'test' => <<<'BLADE'
                    @blaze
                    <div {{ $slot->attributes }}>{{ $slot }}</div>
                    BLADE
                ,
            ],
            view: '<x-test><x-slot name="slot" data-key="1">Content</x-slot></x-test>',
        );

        expect($result)->toContain('data-key="1"');
    });

    it('x-slot:slot preserves attributes', function () {
        $result = blade(
            components: [
                'test' => <<<'BLADE'
                    @blaze
                    <div {{ $slot->attributes }}>{{ $slot }}</div>
                    BLADE
                ,
            ],
            view: '<x-test><x-slot:slot data-key="1">Content</x-slot></x-test>',
        );

        expect($result)->toContain('data-key="1"');
    });
});

describe('slots with props', function () {
    it('works with both props and slots', function () {
        $result = blade(
            components: [
                'card-with-props' => <<<'BLADE'
                    @blaze
                    @props(['title' => 'Default Title'])
                    <div class="card">
                        <div class="card-title">{{ $title }}</div>
                        <div class="card-body">{{ $slot }}</div>
                    </div>
                    BLADE
                ,
            ],
            view: '<x-card-with-props title="Custom Title">Body content</x-card-with-props>',
        );

        expect($result)->toContain('<div class="card-title">Custom Title</div>');
        expect($result)->toContain('<div class="card-body">Body content</div>');
    });

    it('uses prop defaults with slots', function () {
        $result = blade(
            components: [
                'card-with-props' => <<<'BLADE'
                    @blaze
                    @props(['title' => 'Default Title'])
                    <div class="card">
                        <div class="card-title">{{ $title }}</div>
                        <div class="card-body">{{ $slot }}</div>
                    </div>
                    BLADE
                ,
            ],
            view: '<x-card-with-props>Body content</x-card-with-props>',
        );

        expect($result)->toContain('<div class="card-title">Default Title</div>');
    });
});

describe('nested components in slots', function () {
    it('renders nested Blaze component in slot', function () {
        $result = blade(
            components: [
                'card' => <<<'BLADE'
                    @blaze
                    <div class="card">
                        <div class="card-body">{{ $slot }}</div>
                    </div>
                    BLADE
                ,
                'simple-button' => <<<'BLADE'
                    @blaze
                    <button {{ $attributes }}>Click</button>
                    BLADE
                ,
            ],
            view: '<x-card><x-simple-button class="btn" /></x-card>',
        );

        expect($result)->toContain('<div class="card-body">');
        expect($result)->toContain('<button');
        expect($result)->toContain('class="btn"');
    });

    it('renders nested component in named slot', function () {
        $result = blade(
            components: [
                'card-header' => <<<'BLADE'
                    @blaze
                    <div class="card">
                        <div class="card-header">{{ $header ?? '' }}</div>
                        <div class="card-body">{{ $slot }}</div>
                    </div>
                    BLADE
                ,
                'simple-button' => <<<'BLADE'
                    @blaze
                    <button {{ $attributes }}>Click</button>
                    BLADE
                ,
            ],
            view: '<x-card-header><x-slot:header><x-simple-button class="header-btn" /></x-slot:header>Body</x-card-header>',
        );

        expect($result)->toContain('<div class="card-header">');
        expect($result)->toContain('class="header-btn"');
    });

    it('handles deeply nested components', function () {
        $result = blade(
            components: [
                'card' => <<<'BLADE'
                    @blaze
                    <div class="card">
                        <div class="card-body">{{ $slot }}</div>
                    </div>
                    BLADE
                ,
                'simple-button' => <<<'BLADE'
                    @blaze
                    <button {{ $attributes }}>Click</button>
                    BLADE
                ,
            ],
            view: '<x-card><x-card><x-simple-button /></x-card></x-card>',
        );

        expect(substr_count($result, '<div class="card">'))->toBe(2);
        expect($result)->toContain('<button');
    });
});

describe('self-closing components', function () {
    it('renders self-closing component that uses $slot variable', function () {
        $result = blade(
            components: [
                'card' => <<<'BLADE'
                    @blaze
                    <div class="card">
                        <div class="card-body">{{ $slot }}</div>
                    </div>
                    BLADE
                ,
            ],
            view: '<x-card />',
        );

        expect($result)->toContain('<div class="card">');
        expect($result)->toContain('<div class="card-body"></div>');
    });

    it('renders self-closing component with props that uses $slot', function () {
        $result = blade(
            components: [
                'card-with-props' => <<<'BLADE'
                    @blaze
                    @props(['title' => 'Default Title'])
                    <div class="card">
                        <div class="card-title">{{ $title }}</div>
                        <div class="card-body">{{ $slot }}</div>
                    </div>
                    BLADE
                ,
            ],
            view: '<x-card-with-props title="Title" />',
        );

        expect($result)->toContain('<div class="card-title">Title</div>');
        expect($result)->toContain('<div class="card-body"></div>');
    });
});

describe('edge cases', function () {
    it('handles whitespace-only default content', function () {
        $result = blade(
            components: [
                'card' => <<<'BLADE'
                    @blaze
                    <div class="card">
                        <div class="card-body">{{ $slot }}</div>
                    </div>
                    BLADE
                ,
            ],
            view: '<x-card>   </x-card>',
        );

        expect($result)->toContain('<div class="card-body"></div>');
    });

    it('handles newlines and indentation in slot content', function () {
        $result = blade(
            components: [
                'card' => <<<'BLADE'
                    @blaze
                    <div class="card">
                        <div class="card-body">{{ $slot }}</div>
                    </div>
                    BLADE
                ,
            ],
            view: '<x-card>
            <p>Paragraph 1</p>
            <p>Paragraph 2</p>
        </x-card>',
        );

        expect($result)->toContain('<p>Paragraph 1</p>');
        expect($result)->toContain('<p>Paragraph 2</p>');
    });

    it('handles Blade directives in slot content', function () {
        $result = blade(
            components: [
                'card' => <<<'BLADE'
                    @blaze
                    <div class="card">
                        <div class="card-body">{{ $slot }}</div>
                    </div>
                    BLADE
                ,
            ],
            view: '<x-card>
            @if(true)
                Yes
            @endif
        </x-card>',
        );

        expect($result)->toContain('Yes');
    });

    it('handles slot content with PHP expressions', function () {
        $result = blade(
            components: [
                'card' => <<<'BLADE'
                    @blaze
                    <div class="card">
                        <div class="card-body">{{ $slot }}</div>
                    </div>
                    BLADE
                ,
            ],
            view: '<x-card>{{ strtoupper("hello") }}</x-card>',
        );

        expect($result)->toContain('HELLO');
    });

    it('preserves slot content order', function () {
        $result = blade(
            components: [
                'card-full' => <<<'BLADE'
                    @blaze
                    <div class="card">
                        @if(isset($header))
                        <div class="card-header">{{ $header }}</div>
                        @endif
                        <div class="card-body">{{ $slot }}</div>
                        @if(isset($footer))
                        <div class="card-footer">{{ $footer }}</div>
                        @endif
                    </div>
                    BLADE
                ,
            ],
            view: '<x-card-full>
            <x-slot:footer>Footer First</x-slot:footer>
            Body
            <x-slot:header>Header Last</x-slot:header>
        </x-card-full>',
        );

        expect($result)->toContain('<div class="card-header">Header Last</div>');
        expect($result)->toContain('<div class="card-body">Body</div>');
        expect($result)->toContain('<div class="card-footer">Footer First</div>');
    });

    it('handles self-closing slot tag', function () {
        $result = blade(
            components: [
                'test' => <<<'BLADE'
                    @blaze
                    <div>{{ $header ?? "default" }}</div>
                    BLADE
                ,
            ],
            view: '<x-test><x-slot:header /></x-test>',
        );

        expect($result)->toContain('<div></div>');
    });

});

describe('slot whitespace parity with Laravel', function () {
    function assertWhitespaceParity(string $view): void
    {
        $blazeResult = blade(
            components: [
                'wrapper' => <<<'BLADE'
                    @blaze
                    <div>{{ $slot }}</div>
                    BLADE
                ,
            ],
            view: $view,
        );

        $laravelResult = blade(
            components: [
                'wrapper' => <<<'BLADE'
                    <div>{{ $slot }}</div>
                    BLADE
                ,
            ],
            view: $view,
        );

        expect($blazeResult)->toBe(
            $laravelResult,
            "Whitespace mismatch:\nBlaze:   " . json_encode($blazeResult) .
            "\nLaravel: " . json_encode($laravelResult)
        );
    }

    it('matches newline after slot tag', function () {
        assertWhitespaceParity("<x-wrapper>before<x-slot:named>content</x-slot>\nafter</x-wrapper>");
    });

    it('matches newlines on both sides of slot', function () {
        assertWhitespaceParity("<x-wrapper>before\n<x-slot:named>content</x-slot>\nafter</x-wrapper>");
    });

    it('matches double newline after slot', function () {
        assertWhitespaceParity("<x-wrapper>before<x-slot:named>content</x-slot>\n\nafter</x-wrapper>");
    });

    it('matches space after slot tag', function () {
        assertWhitespaceParity('<x-wrapper>before<x-slot:named>content</x-slot> after</x-wrapper>');
    });

    it('matches spaces after slot tag', function () {
        assertWhitespaceParity('<x-wrapper>before<x-slot:named>content</x-slot>   after</x-wrapper>');
    });

    it('matches trailing spaces then newline after slot', function () {
        assertWhitespaceParity("<x-wrapper>before\n<x-slot:named>content</x-slot>   \nafter</x-wrapper>");
    });

    it('matches trailing tabs then newline after slot', function () {
        assertWhitespaceParity("<x-wrapper>before\n<x-slot:named>content</x-slot>\t\t\nafter</x-wrapper>");
    });

    it('matches indented content around slot', function () {
        assertWhitespaceParity("<x-wrapper>\n    before\n    <x-slot:named>content</x-slot>\n    after\n</x-wrapper>");
    });

    it('matches multiple slots with content between', function () {
        assertWhitespaceParity("<x-wrapper>start\n<x-slot:one>first</x-slot>\nmiddle\n<x-slot:two>second</x-slot>\nend</x-wrapper>");
    });

    it('matches mixed tabs and spaces around slot', function () {
        assertWhitespaceParity("<x-wrapper>before\t  \n<x-slot:named>content</x-slot>  \t\nafter</x-wrapper>");
    });

    it('matches slot at very end of content', function () {
        assertWhitespaceParity("<x-wrapper>before<x-slot:named>content</x-slot>\n</x-wrapper>");
    });

    it('matches only slot no loose content', function () {
        assertWhitespaceParity("<x-wrapper><x-slot:named>content</x-slot>\n</x-wrapper>");
    });

    it('matches slot with newline inside content', function () {
        assertWhitespaceParity("<x-wrapper>before<x-slot:named>content\nmore</x-slot>\nafter</x-wrapper>");
    });

    it('matches standard slot syntax', function () {
        assertWhitespaceParity("<x-wrapper>before\n<x-slot name=\"named\">content</x-slot>\nafter</x-wrapper>");
    });

    it('matches explicit default slot with loose content', function () {
        assertWhitespaceParity("<x-wrapper>loose\n<x-slot:slot>explicit</x-slot>\nmore loose</x-wrapper>");
    });

    it('matches three slots with varied whitespace', function () {
        assertWhitespaceParity("<x-wrapper>  start  \n<x-slot:a>A</x-slot>   \n   mid   \n<x-slot:b>B</x-slot>\t\nend</x-wrapper>");
    });

    it('matches slot with only space after closing tag', function () {
        assertWhitespaceParity("<x-wrapper>before\n<x-slot:named>content</x-slot> </x-wrapper>");
    });

    it('matches content with multiple consecutive newlines', function () {
        assertWhitespaceParity("<x-wrapper>before\n\n\n<x-slot:named>content</x-slot>\n\n\nafter</x-wrapper>");
    });

    it('matches deeply indented slot', function () {
        assertWhitespaceParity("<x-wrapper>\n\t\t\tbefore\n\t\t\t<x-slot:named>content</x-slot>\n\t\t\tafter\n</x-wrapper>");
    });

    it('matches slot between two other slots', function () {
        assertWhitespaceParity("<x-wrapper>\n<x-slot:a>A</x-slot>\nmiddle\n<x-slot:b>B</x-slot>\nend\n<x-slot:c>C</x-slot>\n</x-wrapper>");
    });

    it('matches real world card layout', function () {
        assertWhitespaceParity("<x-wrapper>
    <x-slot:header>
        Card Header
    </x-slot>

    Card body content here.

    <x-slot:footer>
        Card Footer
    </x-slot>
</x-wrapper>");
    });
});
