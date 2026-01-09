@blaze
@props(['type' => 'button', 'disabled' => false])

<button type="{{ $type }}" {{ $disabled ? 'disabled' : '' }} {{ $attributes }}>Click</button>
