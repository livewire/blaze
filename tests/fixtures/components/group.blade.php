@pure

@props(['variant' => '', 'dataTest' => 'foo', 'secondVariant' => null])

<div {{ $attributes->merge(['class' => 'group group-'.$variant, 'data-test' => $dataTest]) }}@if($secondVariant) data-second-variant="{{ $secondVariant }}"@endif>{{ $slot }}</div>