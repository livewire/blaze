@blaze

@props(['variant' => 'default'])

<flux:delegate-component :component="'child.' . $variant">Hello World</flux:delegate-component>
