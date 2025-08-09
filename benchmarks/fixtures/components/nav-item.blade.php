@props(['href', 'active' => null])

@php
    $isActive = $active ?? request()->is(trim($href, '/'));
@endphp

<li>
    <a href="{{ $href }}" @class([
        'block px-4 py-2 text-sm rounded-md transition-colors duration-150',
        'bg-blue-100 text-blue-700 font-medium' => $isActive,
        'text-gray-700 hover:bg-gray-100' => !$isActive,
    ])>
        {{ $slot }}
    </a>
</li>