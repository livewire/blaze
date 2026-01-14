<?php

use Livewire\Blaze\Runtime\BlazeAttributeBag;

// Ported from Laravel's ViewComponentAttributeBagTest for compatibility

describe('merge', function () {
    it('merges class attributes by appending', function () {
        $bag = new BlazeAttributeBag(['class' => 'font-bold', 'name' => 'test']);

        expect((string) $bag->merge(['class' => 'mt-4']))
            ->toBe('class="mt-4 font-bold" name="test"');
    });

    it('keeps instance attribute when merging same non-appendable key', function () {
        $bag = new BlazeAttributeBag(['class' => 'font-bold', 'name' => 'test']);

        // name="test" from bag overrides name="foo" from defaults
        expect((string) $bag->merge(['class' => 'mt-4', 'name' => 'foo']))
            ->toBe('class="mt-4 font-bold" name="test"');
    });

    it('adds new attributes from defaults', function () {
        $bag = new BlazeAttributeBag(['class' => 'font-bold', 'name' => 'test']);

        expect((string) $bag->merge(['class' => 'mt-4', 'id' => 'bar']))
            ->toBe('class="mt-4 font-bold" id="bar" name="test"');
    });

    it('preserves order with defaults first', function () {
        $bag = new BlazeAttributeBag(['class' => 'font-bold', 'name' => 'test']);

        expect((string) $bag->merge(['name' => 'default']))
            ->toBe('name="test" class="font-bold"');
    });

    it('works with empty defaults', function () {
        $bag = new BlazeAttributeBag(['class' => 'font-bold', 'name' => 'test']);

        expect((string) $bag->merge([]))
            ->toBe('class="font-bold" name="test"');
    });

    it('works with empty bag', function () {
        $bag = new BlazeAttributeBag([]);

        expect((string) $bag->merge(['class' => 'mt-4']))
            ->toBe('class="mt-4"');
    });

    it('escapes html entities in defaults', function () {
        $bag = new BlazeAttributeBag([]);
        $result = $bag->merge(['test-escaped' => '<tag attr="attr">']);

        expect((string) $result)
            ->toBe('test-escaped="&lt;tag attr=&quot;attr&quot;&gt;"');
    });

    it('handles various value types correctly', function () {
        $bag = new BlazeAttributeBag([
            'test-string' => 'ok',
            'test-null' => null,
            'test-false' => false,
            'test-true' => true,
            'test-0' => 0,
            'test-0-string' => '0',
            'test-empty-string' => '',
        ]);

        expect((string) $bag)
            ->toBe('test-string="ok" test-true="test-true" test-0="0" test-0-string="0" test-empty-string=""');

        expect((string) $bag->merge())
            ->toBe('test-string="ok" test-true="test-true" test-0="0" test-0-string="0" test-empty-string=""');
    });

    it('handles various value types in defaults', function () {
        $bag = (new BlazeAttributeBag())->merge([
            'test-string' => 'ok',
            'test-null' => null,
            'test-false' => false,
            'test-true' => true,
            'test-0' => 0,
            'test-0-string' => '0',
            'test-empty-string' => '',
        ]);

        expect((string) $bag)
            ->toBe('test-string="ok" test-true="test-true" test-0="0" test-0-string="0" test-empty-string=""');
    });

    it('orders appendable attributes before non-appendables after defaults', function () {
        $bag = new BlazeAttributeBag(['disabled' => true, 'class' => 'text-red-500']);

        expect((string) $bag->merge(['type' => 'submit']))
            ->toBe('type="submit" class="text-red-500" disabled="disabled"');
    });

    it('removes duplicate class values when merging', function () {
        $bag = new BlazeAttributeBag(['class' => 'foo']);

        expect((string) $bag->merge(['class' => 'foo']))
            ->toBe('class="foo"');
    });

    it('filters falsey class values when merging', function () {
        $bag = new BlazeAttributeBag(['class' => null]);

        expect((string) $bag->merge(['class' => 'foo']))
            ->toBe('class="foo"');

        $bag = new BlazeAttributeBag(['class' => 0]);

        expect((string) $bag->merge(['class' => 'foo']))
            ->toBe('class="foo"');
    });

    it('keeps empty class attribute when merging without defaults', function () {
        $bag = new BlazeAttributeBag(['class' => '']);

        expect($bag->merge()->all())->toBe(['class' => '']);
    });

    it('keeps appendable defaults unresolved without instance values', function () {
        $bag = new BlazeAttributeBag([]);

        $result = $bag->merge(['class' => $bag->prepends('font-bold')]);

        expect($result->get('class'))->toBeInstanceOf(\Illuminate\View\AppendableAttributeValue::class);
        expect($result->get('class')->value)->toBe('font-bold');
    });

    it('resolves appendable defaults when instance value is empty', function () {
        $bag = new BlazeAttributeBag(['class' => '']);

        $result = $bag->merge(['class' => $bag->prepends('font-bold')]);

        expect($result->get('class'))->toBe('font-bold');
    });

    it('removes duplicate style values when merging', function () {
        $bag = new BlazeAttributeBag(['style' => 'color:red;']);

        expect((string) $bag->merge(['style' => 'color:red;']))
            ->toBe('style="color:red;"');
    });

    it('keeps empty style attribute when merging without defaults', function () {
        $bag = new BlazeAttributeBag(['style' => '']);

        expect((string) $bag->merge())
            ->toBe('style=";"');
    });
});

describe('style merging', function () {
    it('appends style with semicolon', function () {
        $bag = new BlazeAttributeBag(['class' => 'font-bold', 'name' => 'test', 'style' => 'margin-top: 10px']);

        $result = $bag->class(['mt-4', 'ml-2' => true, 'mr-2' => false]);

        // Verify all expected attributes are present with correct values
        // (attribute order may differ from Laravel's Collection-based implementation)
        expect($result->get('class'))->toBe('mt-4 ml-2 font-bold');
        expect($result->get('style'))->toBe('margin-top: 10px;');
        expect($result->get('name'))->toBe('test');
    });

    it('appends style when value already has semicolon', function () {
        $bag = new BlazeAttributeBag(['class' => 'font-bold', 'name' => 'test', 'style' => 'margin-top: 10px; font-weight: bold']);

        $result = $bag->class(['mt-4', 'ml-2' => true, 'mr-2' => false]);

        expect($result->get('class'))->toBe('mt-4 ml-2 font-bold');
        expect($result->get('style'))->toBe('margin-top: 10px; font-weight: bold;');
        expect($result->get('name'))->toBe('test');
    });

    it('merges styles with style method', function () {
        $bag = new BlazeAttributeBag(['class' => 'font-bold', 'name' => 'test', 'style' => 'margin-top: 10px']);

        expect((string) $bag->style(['margin-top: 4px', 'margin-left: 10px;']))
            ->toBe('style="margin-top: 4px; margin-left: 10px; margin-top: 10px;" class="font-bold" name="test"');
    });
});

describe('class method', function () {
    it('merges class string', function () {
        $bag = new BlazeAttributeBag(['class' => 'font-bold', 'name' => 'test']);

        expect((string) $bag->class('mt-4'))
            ->toBe('class="mt-4 font-bold" name="test"');
    });

    it('merges class array', function () {
        $bag = new BlazeAttributeBag(['class' => 'font-bold', 'name' => 'test']);

        expect((string) $bag->class(['mt-4']))
            ->toBe('class="mt-4 font-bold" name="test"');
    });

    it('handles conditional classes', function () {
        $bag = new BlazeAttributeBag(['class' => 'font-bold', 'name' => 'test']);

        expect((string) $bag->class(['mt-4', 'ml-2' => true, 'mr-2' => false]))
            ->toBe('class="mt-4 ml-2 font-bold" name="test"');
    });
});

describe('only and except', function () {
    it('returns only specified attributes', function () {
        $bag = new BlazeAttributeBag(['class' => 'font-bold', 'name' => 'test', 'id' => 'my-id']);

        expect($bag->only('class'))->toBeInstanceOf(BlazeAttributeBag::class);
        expect((string) $bag->only('class'))->toBe('class="font-bold"');
        expect((string) $bag->only(['class', 'name']))->toBe('class="font-bold" name="test"');
        expect((string) $bag->only('missing'))->toBe('');
        expect((string) $bag->only(['name', 'missing']))->toBe('name="test"');
    });

    it('returns all except specified attributes', function () {
        $bag = new BlazeAttributeBag(['class' => 'font-bold', 'name' => 'test', 'id' => 'my-id']);

        expect($bag->except('class'))->toBeInstanceOf(BlazeAttributeBag::class);
        expect((string) $bag->except('class'))->toBe('name="test" id="my-id"');
        expect((string) $bag->except(['class', 'name']))->toBe('id="my-id"');
        expect((string) $bag->except('missing'))->toBe('class="font-bold" name="test" id="my-id"');
        expect((string) $bag->except(['name', 'missing']))->toBe('class="font-bold" id="my-id"');
    });

    it('chains only with merge', function () {
        $bag = new BlazeAttributeBag(['class' => 'font-bold', 'name' => 'test']);

        expect((string) $bag->only('class')->merge(['class' => 'mt-4']))
            ->toBe('class="mt-4 font-bold"');

        expect((string) $bag->merge(['class' => 'mt-4'])->only('class'))
            ->toBe('class="mt-4 font-bold"');
    });
});

describe('whereStartsWith and whereDoesntStartWith', function () {
    it('filters by prefix', function () {
        $bag = new BlazeAttributeBag(['class' => 'font-bold', 'name' => 'test']);

        expect((string) $bag->whereStartsWith('class'))->toBe('class="font-bold"');
        expect((string) $bag->whereDoesntStartWith('class'))->toBe('name="test"');
    });
});

describe('first', function () {
    it('returns first attribute value', function () {
        $bag = new BlazeAttributeBag(['class' => 'font-bold', 'name' => 'test']);

        expect($bag->whereStartsWith('class')->first())->toBe('font-bold');
        expect($bag->whereDoesntStartWith('class')->first())->toBe('test');
    });

    it('returns default when empty', function () {
        $bag = new BlazeAttributeBag([]);

        expect($bag->first('default'))->toBe('default');
    });
});

describe('get', function () {
    it('retrieves attribute value', function () {
        $bag = new BlazeAttributeBag(['class' => 'font-bold', 'name' => 'test']);

        expect($bag->get('class'))->toBe('font-bold');
        expect($bag->get('foo', 'bar'))->toBe('bar');
        expect($bag['class'])->toBe('font-bold');
    });
});

describe('has and missing', function () {
    it('checks attribute existence', function () {
        $bag = new BlazeAttributeBag(['name' => 'test', 'href' => '', 'src' => null]);

        expect($bag->has('src'))->toBeTrue();
        expect($bag->has('href'))->toBeTrue();
        expect($bag->has('name'))->toBeTrue();
        expect($bag->has(['name']))->toBeTrue();
        expect($bag->hasAny(['class', 'name']))->toBeTrue();
        expect($bag->hasAny('class', 'name'))->toBeTrue();
        expect($bag->missing('name'))->toBeFalse();
        expect($bag->has('class'))->toBeFalse();
        expect($bag->has(['class']))->toBeFalse();
        expect($bag->has(['name', 'class']))->toBeFalse();
        expect($bag->has('name', 'class'))->toBeFalse();
        expect($bag->missing('class'))->toBeTrue();
    });
});

describe('isEmpty and isNotEmpty', function () {
    it('checks if empty', function () {
        $emptyBag = new BlazeAttributeBag([]);
        $filledBag = new BlazeAttributeBag(['name' => 'test']);

        expect($emptyBag->isEmpty())->toBeTrue();
        expect($filledBag->isNotEmpty())->toBeTrue();
    });
});

describe('toArray', function () {
    it('returns attributes as array', function () {
        $bag = new BlazeAttributeBag(['name' => 'test', 'class' => 'font-bold']);

        expect($bag->toArray())->toBeArray();
        expect($bag->toArray())->toBe(['name' => 'test', 'class' => 'font-bold']);
    });
});

describe('__toString', function () {
    it('handles boolean true as attribute name', function () {
        $bag = new BlazeAttributeBag(['required' => true, 'disabled' => true]);

        expect((string) $bag)->toBe('required="required" disabled="disabled"');
    });

    it('makes exception for alpine x-data', function () {
        $bag = new BlazeAttributeBag(['required' => true, 'x-data' => true]);

        expect((string) $bag)->toBe('required="required" x-data=""');
    });

    it('makes exception for livewire wire attributes', function () {
        $bag = new BlazeAttributeBag([
            'wire:loading' => true,
            'wire:loading.remove' => true,
            'wire:poll' => true,
        ]);

        expect((string) $bag)->toBe('wire:loading="" wire:loading.remove="" wire:poll=""');
    });

    it('handles dot notation in attribute keys', function () {
        $bag = new BlazeAttributeBag([
            'data.config' => 'value1',
            'x-on:click.prevent' => 'handler',
            'wire:model.lazy' => 'username',
        ]);

        expect($bag->has('data.config'))->toBeTrue();
        expect($bag->get('data.config'))->toBe('value1');
        expect($bag->has('data'))->toBeFalse();
    });
});

describe('__invoke', function () {
    it('can be invoked as callable', function () {
        $bag = new BlazeAttributeBag(['class' => 'font-bold', 'name' => 'test']);

        expect((string) $bag(['class' => 'mt-4']))
            ->toBe('class="mt-4 font-bold" name="test"');

        expect((string) $bag->only('class')(['class' => 'mt-4']))
            ->toBe('class="mt-4 font-bold"');
    });
});

describe('filter', function () {
    it('filters attributes with callback', function () {
        $bag = new BlazeAttributeBag(['class' => 'font-bold', 'name' => 'test', 'id' => 'my-id']);

        $filtered = $bag->filter(fn ($value, $key) => $key !== 'name');

        expect((string) $filtered)->toBe('class="font-bold" id="my-id"');
    });
});

describe('ArrayAccess', function () {
    it('allows array access', function () {
        $bag = new BlazeAttributeBag(['class' => 'font-bold']);

        expect(isset($bag['class']))->toBeTrue();
        expect(isset($bag['missing']))->toBeFalse();
        expect($bag['class'])->toBe('font-bold');

        $bag['id'] = 'test-id';
        expect($bag['id'])->toBe('test-id');

        unset($bag['id']);
        expect(isset($bag['id']))->toBeFalse();
    });
});

describe('IteratorAggregate', function () {
    it('can be iterated', function () {
        $bag = new BlazeAttributeBag(['class' => 'font-bold', 'name' => 'test']);

        $keys = [];
        foreach ($bag as $key => $value) {
            $keys[] = $key;
        }

        expect($keys)->toBe(['class', 'name']);
    });
});

describe('prepends', function () {
    it('appends instance value to default value', function () {
        $bag = new BlazeAttributeBag(['data-controller' => 'outside-controller']);

        $result = $bag->merge(['data-controller' => $bag->prepends('inside-controller')]);

        expect($result->get('data-controller'))->toBe('inside-controller outside-controller');
    });

    it('uses only prepended value when no instance value exists', function () {
        $bag = new BlazeAttributeBag([]);

        $result = $bag->merge(['data-controller' => $bag->prepends('inside-controller')]);

        expect($result->get('data-controller'))->toBeInstanceOf(\Illuminate\View\AppendableAttributeValue::class);
        expect($result->get('data-controller')->value)->toBe('inside-controller');
    });

    it('works with class and style alongside prepends', function () {
        $bag = new BlazeAttributeBag([
            'class' => 'bg-gray-100',
            'data-controller' => 'outside-controller',
            'foo' => 'bar',
        ]);

        $result = $bag->merge([
            'class' => 'mt-4',
            'data-controller' => $bag->prepends('inside-controller'),
        ]);

        expect($result->get('class'))->toBe('mt-4 bg-gray-100');
        expect($result->get('data-controller'))->toBe('inside-controller outside-controller');
        expect($result->get('foo'))->toBe('bar');
    });
});
