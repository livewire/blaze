@blaze(fold: true, unsafe: ['attributes'])

@props(['type' => 'text', 'disabled' => false])

<input
    type="{{ $type }}"
    @if ($disabled) disabled @endif
    @if ($attributes->has('required')) required @endif
>