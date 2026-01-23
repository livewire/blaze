@blaze(fold: true, safe: ['disabled'])

@props(['type' => 'button'])

<button {{ $attributes->merge(['type' => $type]) }}>{{ $slot }}</button>
