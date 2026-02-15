@blaze

<div class="card">
    @if(isset($header))
    <div class="{{ $header->attributes->get('class', '') }}">{{ $header }}</div>
    @endif
    <div class="card-body">{{ $slot }}</div>
</div>
