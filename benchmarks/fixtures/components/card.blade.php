@pure

@props(['title' => '', 'shadow' => true])

<div @class([
    'card',
    'shadow-lg' => $shadow,
    'bg-white rounded-lg p-6'
])>
    @if($title)
        <h3 class="card-title text-xl font-bold mb-4">{{ $title }}</h3>
    @endif
    <div class="card-body">
        {{ $slot }}
    </div>
</div>