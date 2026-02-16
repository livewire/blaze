@blaze(fold: true, safe: ['type'], unsafe: ['required'])

@props(['type' => 'text', 'disabled' => false])

<input
    {{ $attributes }}
    type="{{ $type }}"
    @if ($disabled) aria-disabled="true" @endif
    @if ($attributes->has('required')) aria-required="true" @endif
>