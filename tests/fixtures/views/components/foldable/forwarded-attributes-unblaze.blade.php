@blaze(fold: true, safe: ['title'])

@props(['title'])

<x-foldable.attributes-unblaze :title="$title" data-title="{{ $title }}" />
<x-attributes-unblaze :title="$title" data-title="{{ $title }}" />
