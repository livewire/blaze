@blaze

<div class="card">
    @if(isset($header))
    <div class="card-header">{{ $header }}</div>
    @endif
    <div class="card-body">{{ $slot }}</div>
    @if(isset($footer))
    <div class="card-footer">{{ $footer }}</div>
    @endif
</div>
