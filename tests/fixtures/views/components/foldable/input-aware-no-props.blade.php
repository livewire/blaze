@blaze(fold: true)

@aware(['type'])

{{-- We intentionally do not set @props here to ensure aware props are preserved in $attributes --}}
{{-- When type="number" is passed, below should be rendered as <input type="number" type="number"> --}}

<input {{ $attributes }} type="{{ $type }}" >
