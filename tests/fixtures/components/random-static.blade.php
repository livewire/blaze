@blaze(fold: true)
<div class="static-component">
    <h1>Random: {{ \Illuminate\Support\Str::random(20) }}</h1>
    <p>This should be folded and not change between renders</p>
</div>
