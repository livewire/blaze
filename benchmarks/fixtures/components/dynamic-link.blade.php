@props(['href'])

<a href="{{ $href }}" @class([
    'text-blue-600 hover:text-blue-800 transition-colors duration-200',
    'font-semibold underline' => request()->is(trim($href, '/'))
])>
    {{ $slot }}
</a>