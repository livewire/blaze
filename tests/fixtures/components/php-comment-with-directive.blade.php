@blaze

@props([])

@php
    // This comment mentions @if but should not be compiled as a directive
    $value = 'hello';
@endphp

<div>{{ $value }}</div>
