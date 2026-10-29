<?php

use Livewire\Blaze\Exceptions\AmbiguousAttributeMergeException;
use Livewire\Blaze\Runtime\BlazeAttributeBag;

test('merges equally escaped copies of the same bound attribute into one while folding', function () {
    $bag = new BlazeAttributeBag(['class' => 'BLAZE_PLACEHOLDER_0_&amp;_&amp;amp;_']);

    expect($bag->class('BLAZE_PLACEHOLDER_0_&_&amp;_')->get('class'))->toBe('BLAZE_PLACEHOLDER_0_&amp;_&amp;amp;_');
    expect($bag->merge(['class' => 'BLAZE_PLACEHOLDER_0_&_&amp;_'])->get('class'))->toBe('BLAZE_PLACEHOLDER_0_&amp;_&amp;amp;_');
    expect($bag->merge(['class' => 'BLAZE_PLACEHOLDER_0_&amp;_&amp;amp;_'], false)->get('class'))->toBe('BLAZE_PLACEHOLDER_0_&amp;_&amp;amp;_');
});

test('fails the fold when differently escaped copies of the same bound attribute are merged', function () {
    $bag = new BlazeAttributeBag(['class' => 'BLAZE_PLACEHOLDER_0_&amp;_&amp;amp;_']);

    expect(fn () => $bag->class('BLAZE_PLACEHOLDER_0_&amp;_&amp;amp;_'))->toThrow(AmbiguousAttributeMergeException::class);
    expect(fn () => $bag->merge(['class' => 'BLAZE_PLACEHOLDER_0_&amp;_&amp;amp;_']))->toThrow(AmbiguousAttributeMergeException::class);
    expect(fn () => $bag->merge(['class' => 'BLAZE_PLACEHOLDER_0_&_&amp;_'], false))->toThrow(AmbiguousAttributeMergeException::class);
});

test('merges different attributes and values into class like Blade', function (string $value, string $default, string $expected) {
    $bag = new BlazeAttributeBag(['class' => $value]);

    expect($bag->class($default)->get('class'))->toBe($expected);
})->with([
    'different placeholders' => ['BLAZE_PLACEHOLDER_1_&amp;_&amp;amp;_', 'BLAZE_PLACEHOLDER_0_&_&amp;_', 'BLAZE_PLACEHOLDER_0_&amp;_&amp;amp;_ BLAZE_PLACEHOLDER_1_&amp;_&amp;amp;_'],
    'placeholder and string' => ['BLAZE_PLACEHOLDER_0_&amp;_&amp;amp;_', 'text-sm', 'text-sm BLAZE_PLACEHOLDER_0_&amp;_&amp;amp;_'],
    'strings' => ['p-4', 'text-sm', 'text-sm p-4'],
    'equal strings' => ['text-sm', 'text-sm', 'text-sm'],
]);

test('marks attributes holding a single placeholder when rendered', function (mixed $value, string $expected) {
    expect((string) new BlazeAttributeBag(['class' => $value]))->toBe($expected);
})->with([
    'escaped copy' => ['BLAZE_PLACEHOLDER_0_&amp;_&amp;amp;_', '[BLAZE_ATTR:BLAZE_PLACEHOLDER_0_&amp;_&amp;amp;_:class]'],
    'raw copy' => ['BLAZE_PLACEHOLDER_0_&_&amp;_', '[BLAZE_ATTR:BLAZE_PLACEHOLDER_0_&_&amp;_:class]'],
    'placeholder of an attribute that is not bound' => ['BLAZE_PLACEHOLDER_0_', '[BLAZE_ATTR:BLAZE_PLACEHOLDER_0_:class]'],
    'placeholder with other classes' => ['text-sm BLAZE_PLACEHOLDER_0_&amp;_&amp;amp;_', 'class="text-sm BLAZE_PLACEHOLDER_0_&amp;_&amp;amp;_"'],
    'two placeholders' => ['BLAZE_PLACEHOLDER_0_&amp;_&amp;amp;_ BLAZE_PLACEHOLDER_1_&amp;_&amp;amp;_', 'class="BLAZE_PLACEHOLDER_0_&amp;_&amp;amp;_ BLAZE_PLACEHOLDER_1_&amp;_&amp;amp;_"'],
    'transformed placeholder' => ['BLAZE_PLACEHOLDER_0_&AMP;_', 'class="BLAZE_PLACEHOLDER_0_&AMP;_"'],
    'string' => ['text-sm', 'class="text-sm"'],
]);
