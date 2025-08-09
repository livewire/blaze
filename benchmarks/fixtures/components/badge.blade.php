@pure

@props(['color' => 'gray', 'size' => 'sm'])

<span @class([
    'badge',
    'inline-flex items-center px-3 py-1 rounded-full text-xs font-medium',
    'bg-gray-100 text-gray-800' => $color === 'gray',
    'bg-green-100 text-green-800' => $color === 'green',
    'bg-blue-100 text-blue-800' => $color === 'blue',
    'bg-red-100 text-red-800' => $color === 'red',
    'bg-yellow-100 text-yellow-800' => $color === 'yellow',
])>
    {{ $slot }}
</span>