@blaze

@php
    $type = $attributes->get('type', 'button');
@endphp

<button type="{{ $type }}">Click</button>
