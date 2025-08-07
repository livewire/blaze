<?php

describe('exercise the compiler', function () {
    function compileBlade(string $input): string {
        $parser = app('blaze')->parser();

        return $parser->parse($input, fn ($ast) => $ast);
    }

    it('simple component with static attributes', function () {
        $input = '<x-button size="lg" color="blue">Click Me</x-button>';
        expect(dd(compileBlade($input)))->toBe($input);
    });

    it('self-closing components', function () {
        $input = '<x-icon name="home" size="sm" />';
        expect(compileBlade($input))->toBe($input);
    });

    it('nested components', function () {
        $input = '<x-card><x-button>Save</x-button><x-button>Cancel</x-button></x-card>';
        expect(compileBlade($input))->toBe($input);
    });

    it('components with complex nesting and text', function () {
        $input = '<x-layout title="Dashboard">
    <x-header>
        <x-nav-link href="/home">Home</x-nav-link>
        <x-nav-link href="/about">About</x-nav-link>
    </x-header>
    <main>
        <h1>Welcome back!</h1>
        <x-card>
            <x-card-header>Stats</x-card-header>
            <x-card-body>
                <p>Your dashboard content here</p>
            </x-card-body>
        </x-card>
    </main>
</x-layout>';
        expect(compileBlade($input))->toBe($input);
    });

    it('components with various attribute formats', function () {
        $input = '<x-button type="submit" disabled class="btn-primary" data-test="login-btn">Login</x-button>';
        expect(compileBlade($input))->toBe($input);
    });

    it('components with quoted attributes containing spaces', function () {
        $input = '<x-alert message="This is a long message with spaces" type="warning" />';
        expect(compileBlade($input))->toBe($input);
    });

    it('mixed regular HTML and components', function () {
        $input = '<div class="container">
    <h1>Page Title</h1>
    <x-alert type="success">Operation completed!</x-alert>
    <p>Some regular HTML content</p>
    <x-button onclick="handleClick()">Click me</x-button>
</div>';
        expect(compileBlade($input))->toBe($input);
    });

    it('flux namespace components', function () {
        $input = '<flux:button variant="primary">Flux Button</flux:button>';
        expect(compileBlade($input))->toBe($input);
    });

    it('flux self-closing components', function () {
        $input = '<flux:input type="email" placeholder="Enter email" />';
        expect(compileBlade($input))->toBe($input);
    });

    it('x: namespace components', function () {
        $input = '<x:modal title="Confirm Action">
    <x:button type="danger">Delete</x:button>
    <x:button type="secondary">Cancel</x:button>
</x:modal>';
        expect(compileBlade($input))->toBe($input);
    });

    it('standard slot syntax', function () {
        $input = '<x-modal>
    <x-slot name="header">
        <h2>Modal Title</h2>
    </x-slot>
    <p>Modal content goes here</p>
    <x-slot name="footer">
        <x-button>OK</x-button>
    </x-slot>
</x-modal>';
        expect(compileBlade($input))->toBe($input);
    });

    it('short slot syntax', function () {
        $input = '<x-card>
    <x-slot:header>
        <h3>Card Title</h3>
    </x-slot:header>
    <p>Card body content</p>
    <x-slot:footer>
        <small>Footer text</small>
    </x-slot:footer>
</x-card>';
        expect(compileBlade($input))->toBe($input);
    });

    it('mixed slot syntaxes', function () {
        $input = '<x-layout>
    <x-slot name="title">Page Title</x-slot>
    <x-slot:sidebar>
        <x-nav-item>Home</x-nav-item>
        <x-nav-item>Settings</x-nav-item>
    </x-slot:sidebar>
    <main>Main content</main>
</x-layout>';
        expect(compileBlade($input))->toBe($input);
    });

    it('slots with attributes', function () {
        $input = '<x-modal>
    <x-slot name="header" class="bg-gray-100 p-4">
        <h2>Styled Header</h2>
    </x-slot>
    <x-slot:actions class="flex gap-2">
        <x-button>Save</x-button>
        <x-button>Cancel</x-button>
    </x-slot:actions>
</x-modal>';
        expect(compileBlade($input))->toBe($input);
    });

    it('deeply nested components and slots', function () {
        $input = '<x-page-layout>
    <x-slot:header>
        <x-navigation>
            <x-nav-group title="Main">
                <x-nav-item href="/dashboard">Dashboard</x-nav-item>
                <x-nav-item href="/users">Users</x-nav-item>
            </x-nav-group>
        </x-navigation>
    </x-slot:header>

    <x-content-area>
        <x-card>
            <x-card-header>
                <x-heading level="2">User Management</x-heading>
            </x-card-header>
            <x-card-body>
                <x-data-table>
                    <x-slot name="columns">
                        <x-column>Name</x-column>
                        <x-column>Email</x-column>
                        <x-column>Actions</x-column>
                    </x-slot>
                    <x-row>
                        <x-cell>John Doe</x-cell>
                        <x-cell>john@example.com</x-cell>
                        <x-cell>
                            <x-button size="sm">Edit</x-button>
                        </x-cell>
                    </x-row>
                </x-data-table>
            </x-card-body>
        </x-card>
    </x-content-area>
</x-page-layout>';
        expect(compileBlade($input))->toBe($input);
    });

    it('components with complex attribute values', function () {
        $input = '<x-form method="POST" action="/users/create" :validation-rules="[\'name\' => \'required\', \'email\' => \'required|email\']" class="space-y-4 max-w-md mx-auto" data-turbo="false">
    <x-input name="name" placeholder="Enter your name" />
    <x-input name="email" type="email" placeholder="Enter your email" />
    <x-button type="submit">Create User</x-button>
</x-form>';
        expect(compileBlade($input))->toBe($input);
    });

    it('empty components', function () {
        $input = '<x-divider></x-divider>';
        expect(compileBlade($input))->toBe($input);
    });

    it('components with only whitespace content', function () {
        $input = '<x-container>


</x-container>';
        expect(compileBlade($input))->toBe($input);
    });

    it('special characters in text content', function () {
        $input = '<x-code-block>
if (condition && other_condition) {
    console.log("Hello & welcome!");
    return true;
}
</x-code-block>';
        expect(compileBlade($input))->toBe($input);
    });

    it('components with hyphenated names', function () {
        $input = '<x-user-profile>
    <x-avatar-image src="/avatar.jpg" />
    <x-user-details>
        <x-display-name>John Doe</x-display-name>
        <x-user-email>john@example.com</x-user-email>
    </x-user-details>
</x-user-profile>';
        expect(compileBlade($input))->toBe($input);
    });

    it('components with dots in names', function () {
        $input = '<x-forms.input type="text" name="username" />
<x-forms.select name="country">
    <x-forms.option value="us">United States</x-forms.option>
    <x-forms.option value="ca">Canada</x-forms.option>
</x-forms.select>';
        expect(compileBlade($input))->toBe($input);
    });

    it('mixed component prefixes in same template', function () {
        $input = '<x-layout>
    <flux:header>
        <x:navigation />
    </flux:header>
    <main>
        <x-card>
            <flux:button>Flux Button</flux:button>
            <x:modal>
                <x-content>Mixed prefixes work</x-content>
            </x:modal>
        </x-card>
    </main>
</x-layout>';
        expect(compileBlade($input))->toBe($input);
    });

    it('preserve exact whitespace and formatting', function () {
        $input = '<x-pre-formatted>   This   has   lots   of   spaces   </x-pre-formatted>';
        expect(compileBlade($input))->toBe($input);
    });

    it('attributes with single quotes', function () {
        $input = "<x-component attr='single quoted value' mixed=\"double quoted\">Content</x-component>";
        expect(compileBlade($input))->toBe($input);
    });

    it('attributes with nested quotes', function () {
        $input = '<x-tooltip message="Click the \'Save\' button to continue">Hover me</x-tooltip>';
        expect(compileBlade($input))->toBe($input);
    });
});
