@props(['size' => 'md', 'color' => 'gray'])

<button type="button" class="btn btn-{{ $size }} btn-{{ $color }}">{{ $slot }}</button>