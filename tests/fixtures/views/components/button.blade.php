@pure

@props(['type' => 'button', 'size' => 'md'])

<button type="{{ $type }}" class="btn btn-{{ $size }}">
    {{ $slot }}
</button>