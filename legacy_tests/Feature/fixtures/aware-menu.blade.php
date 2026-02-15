@blaze
@props(['color' => 'gray', 'size' => 'md'])

<ul class="bg-{{ $color }}-100 text-{{ $size }}">
    {{ $slot }}
</ul>
