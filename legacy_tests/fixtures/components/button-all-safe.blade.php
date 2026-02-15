@blaze(fold: true, safe: ['*'])

@props(['type' => 'button', 'label' => 'Click'])

<button type="{{ $type }}" {{ $attributes }}>{{ $label }}</button>
