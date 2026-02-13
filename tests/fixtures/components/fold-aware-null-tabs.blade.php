@blaze(fold: true)

@aware(['variant'])
@props(['variant' => null])

<div class="tabs{{ $variant === null ? ' tabs-border' : ' tabs-'.$variant }}">{{ $slot }}</div>
