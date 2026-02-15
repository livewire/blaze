@blaze(fold: true)
@props(["title"])

@php $x = trim($title); @endphp
<h1>{{ $x }}</h1>
