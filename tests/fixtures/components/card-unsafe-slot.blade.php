@blaze(fold: true, unsafe: ['slot'])

@props([])

<div class="card">{{ $slot ?? '' }}</div>
