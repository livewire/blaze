<?php

use Livewire\Blaze\BladeService;

describe('componentNameToPath', function () {
    beforeEach(function () {
        $basePath = __DIR__ . '/fixtures/components';
        app('blade.compiler')->anonymousComponentPath($basePath);
        app('blade.compiler')->anonymousComponentPath($basePath, 'fixtures');
    });

    describe('namespaced', function () {
        it('gets the correct path for namespaced direct component files', function () {
            $input = 'fixtures::button';
            $expected = __DIR__ . '/fixtures/components/button.blade.php';

            expect((new BladeService)->componentNameToPath($input))->toBe($expected);
        });

        it('prefers namespaced direct component file over index.blade.php for root components', function () {
            $input = 'fixtures::card';
            $expected = __DIR__ . '/fixtures/components/card.blade.php';

            expect((new BladeService)->componentNameToPath($input))->toBe($expected);
        });

        it('gets the correct path for namespaced nested components', function () {
            $input = 'fixtures::form.input';
            $expected = __DIR__ . '/fixtures/components/form/input.blade.php';

            expect((new BladeService)->componentNameToPath($input))->toBe($expected);
        });

        it('gets the correct path for namespaced deeply nested components', function () {
            $input = 'fixtures::form.fields.text';
            $expected = __DIR__ . '/fixtures/components/form/fields/text.blade.php';

            expect((new BladeService)->componentNameToPath($input))->toBe($expected);
        });

        it('gets the correct path for namespaced root components using index.blade.php', function () {
            $input = 'fixtures::form';
            $expected = __DIR__ . '/fixtures/components/form/index.blade.php';

            expect((new BladeService)->componentNameToPath($input))->toBe($expected);
        });

        it('gets the correct path for namespaced root components using same-name.blade.php', function () {
            $input = 'fixtures::panel';
            $expected = __DIR__ . '/fixtures/components/panel/panel.blade.php';

            expect((new BladeService)->componentNameToPath($input))->toBe($expected);
        });

        it('gets the correct path for namespaced nested root components using same-name.blade.php', function () {
            $input = 'fixtures::kanban.comments';
            $expected = __DIR__ . '/fixtures/components/kanban/comments/comments.blade.php';

            expect((new BladeService)->componentNameToPath($input))->toBe($expected);
        });

        it('handles non-existent namespaced components gracefully', function () {
            $input = 'fixtures::nonexistent.component';

            expect((new BladeService)->componentNameToPath($input))->toBe('');
        });
    });

    describe('non-namespaced', function () {
        it('gets the correct path for direct component files', function () {
            $input = 'button';
            $expected = __DIR__ . '/fixtures/components/button.blade.php';

            expect((new BladeService)->componentNameToPath($input))->toBe($expected);
        });

        it('prefers direct component file over index.blade.php for root components', function () {
            $input = 'card';
            $expected = __DIR__ . '/fixtures/components/card.blade.php';

            expect((new BladeService)->componentNameToPath($input))->toBe($expected);
        });

        it('gets the correct path for nested components', function () {
            $input = 'form.input';
            $expected = __DIR__ . '/fixtures/components/form/input.blade.php';

            expect((new BladeService)->componentNameToPath($input))->toBe($expected);
        });

        it('gets the correct path for deeply nested components', function () {
            $input = 'form.fields.text';
            $expected = __DIR__ . '/fixtures/components/form/fields/text.blade.php';

            expect((new BladeService)->componentNameToPath($input))->toBe($expected);
        });

        it('gets the correct path for root components using index.blade.php', function () {
            $input = 'form';
            $expected = __DIR__ . '/fixtures/components/form/index.blade.php';

            expect((new BladeService)->componentNameToPath($input))->toBe($expected);
        });

        it('gets the correct path for root components using same-name.blade.php', function () {
            $input = 'panel';
            $expected = __DIR__ . '/fixtures/components/panel/panel.blade.php';

            expect((new BladeService)->componentNameToPath($input))->toBe($expected);
        });

        it('gets the correct path for nested root components using same-name.blade.php', function () {
            $input = 'kanban.comments';
            $expected = __DIR__ . '/fixtures/components/kanban/comments/comments.blade.php';

            expect((new BladeService)->componentNameToPath($input))->toBe($expected);
        });

        it('handles non-existent components gracefully', function () {
            $input = 'nonexistent.component';

            expect((new BladeService)->componentNameToPath($input))->toBe('');
        });
    });
});
