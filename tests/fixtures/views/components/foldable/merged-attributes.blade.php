@blaze(fold: true, safe: ['title'])

@props(['title' => null])

<span {{ $attributes->merge(['title' => $title]) }}></span>
