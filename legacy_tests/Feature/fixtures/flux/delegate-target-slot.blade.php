@blaze

<div class="inner">
    @isset($header)
        <div class="header">{{ $header }}</div>
    @endisset
    <div class="body">{{ $slot }}</div>
</div>
