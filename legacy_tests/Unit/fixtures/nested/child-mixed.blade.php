@blaze(fold: true)
@props(["title"])

@if($title)
    {{ $title }}
@endif
<div {{ $attributes }}></div>
