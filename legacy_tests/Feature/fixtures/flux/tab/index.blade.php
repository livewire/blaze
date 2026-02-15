@blaze

@aware(['variant'])

@props(['variant' => 'default'])

<flux:delegate-component :component="'tab.variants.' . $variant">{{ $slot }}</flux:delegate-component>
