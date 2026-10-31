@blaze(fold: true)

@php $href = is_string($href = $attributes->get('href')) ? htmlspecialchars_decode($href, ENT_QUOTES) : $href; @endphp

<a href="{!! e($href) !!}">{{ $href }}</a>
