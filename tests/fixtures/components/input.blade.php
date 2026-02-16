@blaze

@props(['type' => 'text', 'disabled' => false])

<input
    {{ $attributes }}
    type="{{ $text }}"
    @if ($disabled) disabled @endif
>