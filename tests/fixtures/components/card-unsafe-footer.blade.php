@blaze(fold: true, unsafe: ['footer'])

@props([])

<div class="card">
    <div class="card-body">{{ $slot }}</div>
    <div class="card-footer">{{ $footer ?? '' }}</div>
</div>
