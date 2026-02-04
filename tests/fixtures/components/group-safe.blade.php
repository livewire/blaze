@blaze(fold: true, safe: ['variant'])

@props(['variant' => ''])

<div class="group group-{{ $variant }}">{{ $slot }}</div>
