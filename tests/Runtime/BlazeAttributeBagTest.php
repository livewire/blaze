<?php

use Livewire\Blaze\Runtime\BlazeAttributeBag;

test('marks attributes holding a single placeholder when rendered', function (mixed $value, string $expected) {
    expect((string) new BlazeAttributeBag(['class' => $value]))->toBe($expected);
})->with([
    'attribute bag copy' => ['BLAZE_PLACEHOLDER_0_bag&_', '[BLAZE_ATTR:BLAZE_PLACEHOLDER_0_bag&_:class]'],
    'raw copy escaped once' => ['BLAZE_PLACEHOLDER_0_raw&amp;_', '[BLAZE_ATTR:BLAZE_PLACEHOLDER_0_raw&amp;_:class]'],
    'placeholder of an attribute that is not bound' => ['BLAZE_PLACEHOLDER_0_', '[BLAZE_ATTR:BLAZE_PLACEHOLDER_0_:class]'],
    'placeholder with other classes' => ['text-sm BLAZE_PLACEHOLDER_0_bag&_', 'class="text-sm BLAZE_PLACEHOLDER_0_bag&_"'],
    'two placeholders' => ['BLAZE_PLACEHOLDER_0_bag&_ BLAZE_PLACEHOLDER_1_bag&_', 'class="BLAZE_PLACEHOLDER_0_bag&_ BLAZE_PLACEHOLDER_1_bag&_"'],
    'transformed placeholder' => ['BLAZE_PLACEHOLDER_0_BAG&_', 'class="BLAZE_PLACEHOLDER_0_BAG&_"'],
    'string' => ['text-sm', 'class="text-sm"'],
]);
