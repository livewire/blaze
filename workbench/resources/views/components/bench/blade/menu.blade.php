@props(['color' => 'gray'])

<ul class="bg-{{ $color }}-100">
    {{ $slot }}
</ul>
