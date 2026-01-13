@blaze

@props(['variant' => 'default'])

<flux:delegate-component :component="'select.variants.' . $variant">{{ $slot }}</flux:delegate-component>
