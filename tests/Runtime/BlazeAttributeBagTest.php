<?php

use Livewire\Blaze\Runtime\BlazeAttributeBag;

test('merges copies of the same bound attribute into one while folding', function (string $value, string $default, string $expected) {
    $bag = new BlazeAttributeBag(['class' => $value]);

    expect($bag->class($default)->get('class'))->toBe($expected);
    expect($bag->merge(['class' => $default])->get('class'))->toBe($expected);
})->with([
    'escaped copy merged into itself' => ['BLAZE_PLACEHOLDER_0_&amp;_', 'BLAZE_PLACEHOLDER_0_&amp;_', 'BLAZE_PLACEHOLDER_0_&amp;amp;_'],
    'raw copy merged into escaped copy' => ['BLAZE_PLACEHOLDER_0_&amp;_', 'BLAZE_PLACEHOLDER_0_&_', 'BLAZE_PLACEHOLDER_0_&amp;_'],
]);

test('merges different attributes and values into class like Blade', function (string $value, string $default, string $expected) {
    $bag = new BlazeAttributeBag(['class' => $value]);

    expect($bag->class($default)->get('class'))->toBe($expected);
})->with([
    'different placeholders' => ['BLAZE_PLACEHOLDER_1_&amp;_', 'BLAZE_PLACEHOLDER_0_&_', 'BLAZE_PLACEHOLDER_0_&amp;_ BLAZE_PLACEHOLDER_1_&amp;_'],
    'placeholder and string' => ['BLAZE_PLACEHOLDER_0_&amp;_', 'text-sm', 'text-sm BLAZE_PLACEHOLDER_0_&amp;_'],
    'strings' => ['p-4', 'text-sm', 'text-sm p-4'],
    'equal strings' => ['text-sm', 'text-sm', 'text-sm'],
]);

test('marks attributes holding a single placeholder when rendered', function (mixed $value, string $expected) {
    expect((string) new BlazeAttributeBag(['class' => $value]))->toBe($expected);
})->with([
    'escaped copy' => ['BLAZE_PLACEHOLDER_0_&amp;_', '[BLAZE_ATTR:BLAZE_PLACEHOLDER_0_&amp;_:class]'],
    'raw copy' => ['BLAZE_PLACEHOLDER_0_&_', '[BLAZE_ATTR:BLAZE_PLACEHOLDER_0_&_:class]'],
    'placeholder of an attribute that is not bound' => ['BLAZE_PLACEHOLDER_0_', '[BLAZE_ATTR:BLAZE_PLACEHOLDER_0_:class]'],
    'placeholder with other classes' => ['text-sm BLAZE_PLACEHOLDER_0_&amp;_', 'class="text-sm BLAZE_PLACEHOLDER_0_&amp;_"'],
    'two placeholders' => ['BLAZE_PLACEHOLDER_0_&amp;_ BLAZE_PLACEHOLDER_1_&amp;_', 'class="BLAZE_PLACEHOLDER_0_&amp;_ BLAZE_PLACEHOLDER_1_&amp;_"'],
    'transformed placeholder' => ['BLAZE_PLACEHOLDER_0_&AMP;_', 'class="BLAZE_PLACEHOLDER_0_&AMP;_"'],
    'string' => ['text-sm', 'class="text-sm"'],
]);
