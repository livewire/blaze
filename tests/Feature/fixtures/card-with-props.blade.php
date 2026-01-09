@blaze
@props(['title' => 'Default Title'])

<div class="card">
    <div class="card-title">{{ $title }}</div>
    <div class="card-body">{{ $slot }}</div>
</div>
