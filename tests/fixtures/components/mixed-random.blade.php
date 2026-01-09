@blaze(fold: true)
<div class="mixed-component">
    <h1>Static Random: {{ \Illuminate\Support\Str::random(20) }}</h1>
    @unblaze
        <p class="dynamic">Dynamic value: {{ $dynamicValue ?? 'none' }}</p>
    @endunblaze
    <footer>Static Footer: {{ \Illuminate\Support\Str::random(10) }}</footer>
</div>
