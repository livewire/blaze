@props(['title'])

<div class="card">
    <h2>{{ $title }}</h2>
    <div class="card-body">
        {{ $slot }}
    </div>
</div>