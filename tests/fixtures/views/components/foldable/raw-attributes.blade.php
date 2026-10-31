@blaze(fold: true, safe: ['content'])

@props(['content' => null])

<div title="{{ $content }}">{!! $content !!}</div>
