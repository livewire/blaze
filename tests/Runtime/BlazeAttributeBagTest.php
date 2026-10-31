<?php

use Livewire\Blaze\Runtime\BlazeAttributeBag;

test('marks attributes holding a single placeholder when rendered', function (mixed $value, string $expected) {
    expect((string) new BlazeAttributeBag(['class' => $value]))->toBe($expected);
})->with([
    'attribute bag copy' => ['BLAZE_PLACEHOLDER_0_bag&_&amp;_', '[BLAZE_ATTR:BLAZE_PLACEHOLDER_0_bag&_&amp;_:class]'],
    'raw copy escaped once' => ['BLAZE_PLACEHOLDER_0_raw&amp;_&amp;amp;_', '[BLAZE_ATTR:BLAZE_PLACEHOLDER_0_raw&amp;_&amp;amp;_:class]'],
    'placeholder of an attribute that is not bound' => ['BLAZE_PLACEHOLDER_0_', '[BLAZE_ATTR:BLAZE_PLACEHOLDER_0_:class]'],
    'placeholder with other classes' => ['text-sm BLAZE_PLACEHOLDER_0_bag&_&amp;_', 'class="text-sm BLAZE_PLACEHOLDER_0_bag&_&amp;_"'],
    'two placeholders' => ['BLAZE_PLACEHOLDER_0_bag&_&amp;_ BLAZE_PLACEHOLDER_1_bag&_&amp;_', 'class="BLAZE_PLACEHOLDER_0_bag&_&amp;_ BLAZE_PLACEHOLDER_1_bag&_&amp;_"'],
    'transformed placeholder' => ['BLAZE_PLACEHOLDER_0_BAG&_&AMP;_', 'class="BLAZE_PLACEHOLDER_0_BAG&_&AMP;_"'],
    'string' => ['text-sm', 'class="text-sm"'],
]);
