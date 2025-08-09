@pure

@props(['variant' => 'primary', 'size' => 'md'])

<button type="button" class="btn btn-{{ $variant }} btn-{{ $size }}">
    {{ $slot }}
</button>