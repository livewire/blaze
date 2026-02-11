<?php

use Livewire\Blaze\Support\AttributeParser;

beforeEach(function () {
    app('blade.compiler')->anonymousComponentPath(__DIR__.'/Feature/fixtures');
});

describe('parsing', function () {
    it('transforms @class into a dynamic :class attribute', function () {
        $input = '@class([\'active\' => $isActive])';
        $attributes = (new AttributeParser)->parseAttributeStringToArray($input);

        expect($attributes)->toHaveKey('class');
        expect($attributes['class']['isDynamic'])->toBeTrue();
        expect($attributes['class']['value'])->toBe('\Illuminate\Support\Arr::toCssClasses([\'active\' => $isActive])');
        expect($attributes['class']['original'])->toBe(':class="\Illuminate\Support\Arr::toCssClasses([\'active\' => $isActive])"');
    });

    it('transforms @style into a dynamic :style attribute', function () {
        $input = '@style([\'color: red\' => $isRed])';
        $attributes = (new AttributeParser)->parseAttributeStringToArray($input);

        expect($attributes)->toHaveKey('style');
        expect($attributes['style']['isDynamic'])->toBeTrue();
        expect($attributes['style']['value'])->toBe('\Illuminate\Support\Arr::toCssStyles([\'color: red\' => $isRed])');
    });

    it('handles @class with nested parentheses', function () {
        $input = '@class([\'active\' => (bool) ($count > 0)])';
        $attributes = (new AttributeParser)->parseAttributeStringToArray($input);

        expect($attributes)->toHaveKey('class');
        expect($attributes['class']['isDynamic'])->toBeTrue();
        expect($attributes['class']['value'])->toContain('toCssClasses');
        expect($attributes['class']['value'])->toContain('(bool) ($count > 0)');
    });

    it('preserves other attributes alongside @class', function () {
        $input = 'type="button" @class([\'active\' => $isActive]) :disabled="$isDisabled" searchable';
        $attributes = (new AttributeParser)->parseAttributeStringToArray($input);

        expect($attributes)->toHaveCount(4);
        expect($attributes)->toHaveKeys(['type', 'class', 'disabled', 'searchable']);

        expect($attributes['type']['isDynamic'])->toBeFalse();
        expect($attributes['type']['value'])->toBe('button');

        expect($attributes['class']['isDynamic'])->toBeTrue();
        expect($attributes['class']['value'])->toContain('toCssClasses');

        expect($attributes['disabled']['isDynamic'])->toBeTrue();
        expect($attributes['disabled']['value'])->toBe('$isDisabled');

        expect($attributes['searchable']['isDynamic'])->toBeFalse();
        expect($attributes['searchable']['value'])->toBeTrue();
    });

    it('converts double quotes to single quotes inside @class arguments', function () {
        $input = '@class(["active" => $isActive])';
        $attributes = (new AttributeParser)->parseAttributeStringToArray($input);

        expect($attributes['class']['value'])->toBe('\Illuminate\Support\Arr::toCssClasses([\'active\' => $isActive])');
    });

    it('throws for unsupported @disabled directive', function () {
        $input = '@disabled($condition)';
        (new AttributeParser)->parseAttributeStringToArray($input);
    })->throws(InvalidArgumentException::class, '[@disabled(...)] is not supported on component tags');

    it('throws for unsupported @checked directive', function () {
        $input = '@checked($condition)';
        (new AttributeParser)->parseAttributeStringToArray($input);
    })->throws(InvalidArgumentException::class, '[@checked(...)] is not supported on component tags');

    it('throws for unsupported @selected directive', function () {
        $input = '@selected($condition)';
        (new AttributeParser)->parseAttributeStringToArray($input);
    })->throws(InvalidArgumentException::class, '[@selected(...)] is not supported on component tags');

    it('throws for unsupported @required directive', function () {
        $input = '@required($condition)';
        (new AttributeParser)->parseAttributeStringToArray($input);
    })->throws(InvalidArgumentException::class, '[@required(...)] is not supported on component tags');

    it('throws for unsupported @readonly directive', function () {
        $input = '@readonly($condition)';
        (new AttributeParser)->parseAttributeStringToArray($input);
    })->throws(InvalidArgumentException::class, '[@readonly(...)] is not supported on component tags');

    it('does not throw for Alpine @click directive', function () {
        $input = '@click="open = true"';
        $attributes = (new AttributeParser)->parseAttributeStringToArray($input);

        // @click="..." uses = syntax, not (), so validation does not trigger.
        // Note: the attribute is not parsed (no regex matches @-prefixed names)
        // but it does not throw either — matching pre-existing behavior.
        expect(fn () => (new AttributeParser)->parseAttributeStringToArray($input))->not->toThrow(InvalidArgumentException::class);
    });

    it('does not throw for Alpine @click.prevent directive', function () {
        expect(fn () => (new AttributeParser)->parseAttributeStringToArray('@click.prevent="submit"'))
            ->not->toThrow(InvalidArgumentException::class);
    });
});

describe('rendering', function () {
    it('renders @class with true condition', function () {
        $result = blade(
            components: [
                'badge' => <<<'BLADE'
                    @blaze(fold: true, safe: ['class'])
                    <span {{ $attributes }}>{{ $slot }}</span>
                    BLADE
                ,
            ],
            view: '<x-badge @class([\'active\' => $isActive])>Tag</x-badge>',
            data: ['isActive' => true],
        );

        expect($result)->toContain('class="active"');
    });

    it('renders @class with false condition as empty class', function () {
        $result = blade(
            components: [
                'badge' => <<<'BLADE'
                    @blaze(fold: true, safe: ['class'])
                    <span {{ $attributes }}>{{ $slot }}</span>
                    BLADE
                ,
            ],
            view: '<x-badge @class([\'active\' => $isActive])>Tag</x-badge>',
            data: ['isActive' => false],
        );

        // Arr::toCssClasses returns empty string when all conditions are false,
        // which is rendered as class="" (matching standard Blade behavior)
        expect($result)->toContain('class=""');
        expect($result)->not->toContain('class="active"');
    });

    it('renders @style with true condition', function () {
        $result = blade(
            components: [
                'box' => <<<'BLADE'
                    @blaze(fold: true, safe: ['style'])
                    <div {{ $attributes }}>{{ $slot }}</div>
                    BLADE
                ,
            ],
            view: '<x-box @style([\'color: red\' => $isRed])>Content</x-box>',
            data: ['isRed' => true],
        );

        // Arr::toCssStyles adds trailing semicolons to style values
        expect($result)->toContain('style="color: red;"');
    });

    it('renders @style with false condition as empty style', function () {
        $result = blade(
            components: [
                'box' => <<<'BLADE'
                    @blaze(fold: true, safe: ['style'])
                    <div {{ $attributes }}>{{ $slot }}</div>
                    BLADE
                ,
            ],
            view: '<x-box @style([\'color: red\' => $isRed])>Content</x-box>',
            data: ['isRed' => false],
        );

        // Arr::toCssStyles returns empty string when all conditions are false,
        // which is rendered as style="" (matching standard Blade behavior)
        expect($result)->toContain('style=""');
        expect($result)->not->toContain('color: red');
    });

    it('renders @class alongside other attributes', function () {
        $result = blade(
            components: [
                'button' => <<<'BLADE'
                    @blaze(fold: true, safe: ['class'])
                    @props(['type' => 'button'])
                    <button {{ $attributes->merge(['type' => $type]) }}>{{ $slot }}</button>
                    BLADE
                ,
            ],
            view: '<x-button @class([\'btn-primary\' => $isPrimary]) id="save">Save</x-button>',
            data: ['isPrimary' => true],
        );

        expect($result)->toContain('class="btn-primary"');
        expect($result)->toContain('id="save"');
        expect($result)->toContain('type="button"');
    });

    it('renders @class with multiple conditional classes', function () {
        $result = blade(
            components: [
                'badge' => <<<'BLADE'
                    @blaze(fold: true, safe: ['class'])
                    <span {{ $attributes }}>{{ $slot }}</span>
                    BLADE
                ,
            ],
            view: '<x-badge @class([\'font-bold\', \'text-red\' => $isError, \'text-green\' => $isSuccess])>Status</x-badge>',
            data: ['isError' => true, 'isSuccess' => false],
        );

        expect($result)->toContain('font-bold');
        expect($result)->toContain('text-red');
        expect($result)->not->toContain('text-green');
    });

    it('renders @class without folding', function () {
        $result = blade(
            components: [
                'badge' => <<<'BLADE'
                    @blaze
                    <span {{ $attributes }}>{{ $slot }}</span>
                    BLADE
                ,
            ],
            view: '<x-badge @class([\'active\' => $isActive])>Tag</x-badge>',
            data: ['isActive' => true],
        );

        expect($result)->toContain('class="active"');
    });
});
