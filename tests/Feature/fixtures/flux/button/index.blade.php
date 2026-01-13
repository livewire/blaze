@blaze

@props(['variant' => 'default'])

<flux:delegate-component :component="'button.variants.' . $variant">{{ $slot }}</flux:delegate-component>
