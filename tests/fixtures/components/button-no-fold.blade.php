@props(['type' => 'button'])

<button {{ $attributes->merge(['type' => $type]) }}>{{ $slot }}</button>