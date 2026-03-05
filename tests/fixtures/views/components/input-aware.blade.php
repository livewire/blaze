@blaze

@aware(['type' => 'text'])

@props(['type' => 'text', 'disabled' => false])


<input
    {{ $attributes }}
    type="{{ $type }}"
    @if ($disabled) disabled @endif
>