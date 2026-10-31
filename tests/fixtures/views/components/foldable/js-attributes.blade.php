@blaze(fold: true, safe: ['name'])

@props(['name' => null])

<div x-data="{ name: @js($name) }"></div>
