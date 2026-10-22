@blaze

@props([
    'variant' => 'default',
])

<flux:delegate-component :component="'delegated.variants.' . $variant">{{ $slot }}</flux:delegate-component>
